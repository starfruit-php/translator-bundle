<?php

namespace Starfruit\TranslatorBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('starfruit_translator');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('object')
                ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('class_need_translate')
                            ->info('List of Class, using Class name as key')
                            ->arrayPrototype()
                                ->children()
                                    ->arrayNode('field_need_translate')
                                        ->info('List of field in Class')
                                        ->prototype('scalar')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('collection_need_translate')
                            ->info('List of Field-Collection, using name as key')
                            ->arrayPrototype()
                                ->children()
                                    ->arrayNode('field_need_translate')
                                        ->info('List of field in Class')
                                        ->prototype('scalar')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
