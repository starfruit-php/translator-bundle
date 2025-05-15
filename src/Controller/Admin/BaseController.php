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

class BaseController extends \Pimcore\Controller\FrontendController
{
    const DEFAULT_LANG = 'vi';

    protected $config;
    protected $classNeedTranslateList;
    protected $collectionNeedTranslateList;
    protected $translatorApiEndpoint;

    public function __construct(protected ApplicationLogger $logger)
    {
        $this->config = \Pimcore::getContainer()->getParameter('starfruit_translator');
        $this->classNeedTranslateList = $this->getClassNeedTranslateList();
        $this->collectionNeedTranslateList = $this->getCollectionNeedTranslateList();
        $this->translatorApiEndpoint = Config::getWebsiteConfigValue('translator_api_endpoint');

        if (!$this->translatorApiEndpoint) {
            return new Response('Null API endpoint!', Response::HTTP_BAD_REQUEST);
        }
    }

    public function sendResponse($response)
    {
        return new JsonResponse($response, Response::HTTP_OK);
    }

    protected function getClassNeedTranslateList()
    {
        if (!isset($this->config['object']['class_need_translate'])) return [];

        return (array) $this->config['object']['class_need_translate'];
    }

    protected function getCollectionNeedTranslateList()
    {
        if (!isset($this->config['object']['collection_need_translate'])) return [];

        return (array) $this->config['object']['collection_need_translate'];
    }

    protected function translate($language_code, $content, $logTitle)
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

    protected function getToken()
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
