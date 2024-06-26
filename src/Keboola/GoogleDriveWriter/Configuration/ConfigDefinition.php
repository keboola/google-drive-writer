<?php

declare(strict_types=1);

namespace Keboola\GoogleDriveWriter\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ConfigDefinition implements ConfigurationInterface
{
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';

    /**
     * Generates the configuration tree builder.
     *
     * @return TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('parameters');

        $rootNode
            ->children()
                ->scalarNode('data_dir')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('tables')
                    ->prototype('array')
                        ->children()
                            ->integerNode('id')
                                ->isRequired()
                                ->min(0)
                            ->end()
                            ->scalarNode('tableId')
                            ->end()
                            ->scalarNode('fileId')
                            ->end()
                            ->scalarNode('title')
                                ->cannotBeEmpty()
                            ->end()
                            ->arrayNode('folder')
                                ->children()
                                    ->scalarNode('id')
                                    ->end()
                                    ->scalarNode('title')
                                    ->end()
                                ->end()
                            ->end()
                            ->enumNode('action')
                                ->values(['create', 'update'])
                            ->end()
                            ->booleanNode('enabled')
                                ->defaultValue(true)
                            ->end()
                            ->booleanNode('convert')
                                ->defaultValue(false)
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('files')
                    ->children()
                        ->arrayNode('folder')
                            ->children()
                                ->scalarNode('id')
                                ->end()
                                ->scalarNode('title')
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
