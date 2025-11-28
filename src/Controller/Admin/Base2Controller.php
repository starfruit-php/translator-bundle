<?php

namespace Starfruit\TranslatorBundle\Controller\Admin;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Pimcore\Config;
use Pimcore\Bundle\ApplicationLoggerBundle\FileObject;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Model\WebsiteSetting;

class Base2Controller extends \Pimcore\Controller\FrontendController
{
    const DEFAULT_LANG = 'vi';
    const DEFAULT_VERSION_NOTE = 'stf_translator_version';

    public function __construct(protected ApplicationLogger $logger)
    {
    }

    public function sendResponse($response)
    {
        return new JsonResponse($response, Response::HTTP_OK);
    }

    protected function getConfig()
    {
        return \Pimcore::getContainer()->getParameter('starfruit_translator');
    }

    protected function getClassNeedTranslateList()
    {
        $config = $this->getConfig();
        if (!isset($config['object']['class_need_translate'])) return [];

        return (array) $config['object']['class_need_translate'];
    }

    protected function getCollectionNeedTranslateList()
    {
        $config = $this->getConfig();
        if (!isset($config['object']['collection_need_translate'])) return [];

        return (array) $config['object']['collection_need_translate'];
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
            $translatorApiEndpoint = Config::getWebsiteConfigValue('translator_api_endpoint');
            $call = $client->post($translatorApiEndpoint, $params);
        } catch (\Throwable $e) {
            return [];
        }

        $responseContents = $call->getBody()->getContents();

        // log
        if (Config::getWebsiteConfigValue('translator_enable_log')) {
            $fileObject = new FileObject("$content\n\n\n\n\n===== $language_code =====>\n\n\n\n\n $responseContents");
            $this->logger->info("$logTitle [$language_code]", [
                'fileObject'    => $fileObject,
                'component'     => $language_code
            ]);
        }   

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
