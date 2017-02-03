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
    const TYPE_FILE = 'file';
    const TYPE_SPREADSHEET = 'spreadsheet';

    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_APPEND = 'append';

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
                ->arrayNode('files')
                    ->isRequired()
                    ->prototype('array')
                        ->children()
                            ->integerNode('id')
                                ->isRequired()
                                ->min(0)
                            ->end()
                            ->scalarNode('fileId')
                            ->end()
                            ->scalarNode('title')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->arrayNode('parents')
                                ->prototype('scalar')->end()
                            ->end()
                            ->enumNode('type')
                                ->values(['file', 'spreadsheet'])
                            ->end()
                            ->enumNode('action')
                                // ->isRequired() @todo make required if type == 'file'
                                ->values(['create', 'update'])
                            ->end()
                            ->scalarNode('tableId')
                                // ->isRequired() @todo make required if type == 'file'
                            ->end()
                            ->booleanNode('enabled')
                                // ->isRequired() @todo make required if type == 'file'
                                ->defaultValue(true)
                            ->end()
                            ->arrayNode('sheets')
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('sheetId')
                                            ->isRequired()
                                            ->cannotBeEmpty()
                                        ->end()
                                        ->scalarNode('title')
                                            ->isRequired()
                                            ->cannotBeEmpty()
                                        ->end()
                                        ->enumNode('action')
                                            ->values(['update', 'append'])
                                        ->end()
                                        ->scalarNode('tableId')
                                            ->isRequired()
                                        ->end()
                                        ->booleanNode('enabled')
                                            ->defaultValue(true)
                                        ->end()
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
