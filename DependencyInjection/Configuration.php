<?php

namespace Akyos\UXExport\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ux_export');

        $treeBuilder
            ->getRootNode()
            ->children()
            ->scalarNode('path')->defaultValue('public/')->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
