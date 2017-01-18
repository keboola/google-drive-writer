<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/08/16
 * Time: 15:50
 */

namespace Keboola\GoogleDriveWriter\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ConfigDefinition implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('parameters');

        $rootNode
            ->children()
                ->scalarNode('data_dir')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('sheets')
                    ->isRequired()
                    ->prototype('array')
                        ->children()
                            ->integerNode('id')
                                ->isRequired()
                                ->min(0)
                            ->end()
                            ->scalarNode('fileId')
                            ->end()
                            ->scalarNode('fileTitle')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('sheetId')
                            ->end()
                            ->scalarNode('sheetTitle')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->arrayNode('parents')
                                ->prototype('scalar')->end()
                            ->end()
                            ->enumNode('type')
                                ->values(['file', 'sheet'])
                            ->end()
                            ->enumNode('action')
                                ->values(['create', 'update', 'append'])
                            ->end()
                            ->booleanNode('enabled')
                                ->defaultValue(true)
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
