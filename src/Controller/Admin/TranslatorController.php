<?php

namespace Starfruit\TranslatorBundle\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

use Pimcore\Db;
use Pimcore\Config;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Pimcore\Model\Version;
use Pimcore\Model\Element;
use Pimcore\Bundle\ApplicationLoggerBundle\FileObject;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Model\WebsiteSetting;

/**
 * @Route("/translator")
 */
class TranslatorController extends \Pimcore\Controller\FrontendController
{
    const DEFAULT_LANG = 'vi';

    private $config;
    private $classNeedTranslateList;
    private $translatorApiEndpoint;

    public function __construct(protected ApplicationLogger $logger)
    {
        $this->config = \Pimcore::getContainer()->getParameter('starfruit_translator');
        $this->classNeedTranslateList = $this->getClassNeedTranslateList();
        $this->translatorApiEndpoint = Config::getWebsiteConfigValue('translator_api_endpoint');

        if (!$this->translatorApiEndpoint) {
            return new Response('Null API endpoint!', Response::HTTP_BAD_REQUEST);
        }
    }

    public function sendResponse($response)
    {
        return new JsonResponse($response, Response::HTTP_OK);
    }

    private function getClassNeedTranslateList()
    {
        if (!isset($this->config['object']['class_need_translate'])) return [];

        return (array) $this->config['object']['class_need_translate'];
    }

    /**
     * @Route("/can-translate/{id}", methods={"POST"})
     */
    public function canTranslateAction($id)
    {
        if (empty($this->classNeedTranslateList)) return $this->sendResponse(['ok' => false]);

        $object = DataObject::getById((int) $id);
        $validObject = $object && !($object instanceof DataObject\Folder);

        if (!$validObject) return $this->sendResponse(['ok' => false]);

        $canTranslate = array_key_exists($object->getClassname(), $this->classNeedTranslateList);

        return $this->sendResponse(['ok' => $canTranslate]);
    }

    /**
     * @Route("/object/{id}/{language}", methods={"POST"})
     */
    public function objectAction($id, $language)
    {
        $object = DataObject::getById((int) $id);

        // validate
        if (!$language || $language == 'vi') return $this->sendResponse('Invalid language!');

        $validObject = $object && !($object instanceof DataObject\Folder);
        if (!$validObject) return $this->sendResponse('Invalid object!');

        $classname = $object->getClassname();
        $canTranslate = array_key_exists($classname, $this->classNeedTranslateList);
        if (!$canTranslate) return $this->sendResponse('No config for Class of object!');

        $classConfig = $this->classNeedTranslateList[$classname];
        $fieldNeedTranslates = isset($classConfig['field_need_translate']) ? $classConfig['field_need_translate'] : [];
        if (empty($fieldNeedTranslates)) return $this->sendResponse('No fields config for translate!');

        // get lastest data
        $list = new Version\Listing();
        $list->setLoadAutoSave(true);
        $list->setCondition('cid = ? AND ctype = ?', [
            $object->getId(),
            Element\Service::getElementType($object)
        ])
            ->setOrderKey('date')
            ->setOrder('DESC');

        $versions = $list->load();
        $lastestVersion = empty($versions) ? null : $versions[0];
        $lastestData = $lastestVersion?->getData() ?: $object;

        // translate
        $logTitle = "Object - $id";
        $translatedData = [];
        foreach ($fieldNeedTranslates as $field) {
            $function = 'get' . ucfirst($field);

            if (!method_exists($lastestData, $function)) {
                continue;
            }

            $content = $lastestData->{$function}(self::DEFAULT_LANG);

            if (!$content) {
                continue;
            }

            $targetContent = $this->translate($language, $content, $logTitle);

            if (empty($targetContent)) {
                continue;
            }

            $translatedData[$field] = $targetContent;
        }

        if (!empty($translatedData)) {
            foreach ($translatedData as $field => $value) {
                $function = 'set' . ucfirst($field);

                $lastestData->{$function}($value, $language);
            }
            $lastestData->saveVersion();
        }

        return $this->sendResponse('Success!');
    }

    /**
     * @Route("/document/{id}/{language}/{sourceId}", methods={"POST"})
     */
    public function documentAction($id, $language, $sourceId)
    {
        // valid
        $document = Document::getById($id);
        if (!($document instanceof Document\Page || $document instanceof Document\Snippet)) return $this->sendResponse('Document type must be page or snippet!');

        $propertyLanguage = $document->getProperty('language');
        if ($propertyLanguage == 'vi' || $propertyLanguage != $language) return $this->sendResponse('Invalid language!');

        // query DB
        $db = Db::get();
        $query = "SELECT * FROM `documents_editables` WHERE `documentId` = ? AND `type` IN ('input', 'textarea', 'wysiwyg') AND `name` NOT REGEXP '^[^:]+:[0-9]+\\.[^\\.]+$'";
        $sourceData = $db->fetchAllAssociative($query, [$sourceId]);

        if (empty($sourceData)) return $this->sendResponse('No content need translate!');

        // translate
        $logTitle = "Document - $id";
        foreach ($sourceData as $sourceItem) {
            $content = $sourceItem['data'];
            if (empty($content)) {
                continue;
            }

            $targetContent = $this->translate($language, $content, $logTitle);

            if (empty($targetContent)) {
                continue;
            }

            // update to DB
            $query = "UPDATE `documents_editables` SET `data` = ? WHERE `documentId` = ? AND `type` = ? AND `name` = ?";
            $db->executeQuery($query, [$targetContent, $id, $sourceItem['type'], $sourceItem['name']]);
        }

        return $this->sendResponse('Success!');
    }

    private function translate($language_code, $content, $logTitle)
    {
        $token = $this->getToken();
        $token = trim($token);
        try {
            $params = [
                "headers" => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
                "body" => json_encode([
                    'language_code' => $language_code,
                    'content' => $content
                ]),
                // 'connect_timeout' => 5,
                // 'timeout' => 5
            ];
            
            $client = new \GuzzleHttp\Client();
            $call = $client->post($this->translatorApiEndpoint, $params);
        } catch (\Throwable $e) {
            return [];
        }

        $responseContents = $call->getBody()->getContents();

        // log
        $fileObject = new FileObject("$content\n\n\n\n\n===== $language_code =====>\n\n\n\n\n $responseContents");
        $this->logger->info("$logTitle [$language_code]", [
            'fileObject'    => $fileObject,
            'component'     => $language_code
        ]);

        $response = json_decode($responseContents, true);
        if (is_array($response) && count($response) == 1 && isset($response[0]['translation'])) {
            return $response[0]['translation'];
        }

        return null;
    }

    private function getToken()
    {
        $renewTokenAt = (int) Config::getWebsiteConfigValue('translator_token_renew_at');
        $token = Config::getWebsiteConfigValue('translator_token');

        if ($renewTokenAt < time()) {
            $command = 'gcloud auth print-identity-token';
            $token = shell_exec($command);

            // store
            $renewTokenAtWS = WebsiteSetting::getByName('translator_token_renew_at');
            if (!$renewTokenAtWS) {
                $renewTokenAtWS = new WebsiteSetting();
                $renewTokenAtWS->setName('translator_token_renew_at');
                $renewTokenAtWS->setType('text');
            }

            $renewTokenAtWS->setData((string) (time() + 3500));
            $renewTokenAtWS->save();

            $tokeWS = WebsiteSetting::getByName('translator_token');
            if (!$tokeWS) {
                $tokeWS = new WebsiteSetting();
                $tokeWS->setName('translator_token');
                $tokeWS->setType('text');
            }

            $tokeWS->setData($token);
            $tokeWS->save();
        }
        
        return $token;
    }
}
