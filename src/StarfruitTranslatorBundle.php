<?php

namespace Starfruit\TranslatorBundle;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\PimcoreBundleAdminClassicInterface;
use Pimcore\Extension\Bundle\Traits\BundleAdminClassicTrait;
use Pimcore\Config;

class StarfruitTranslatorBundle extends AbstractPimcoreBundle implements PimcoreBundleAdminClassicInterface
{
    use BundleAdminClassicTrait;

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getJsPaths(): array
    {
        $scriptPath = Config::getWebsiteConfigValue('translator_enable_script_v1')
            ? '/bundles/starfruittranslator/js/pimcore/translator-button.js'
            : '/bundles/starfruittranslator/js/pimcore/translator-button-v2.js';

        return [
            '/bundles/starfruittranslator/js/pimcore/startup.js',
            $scriptPath,
        ];
    }

}