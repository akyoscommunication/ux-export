<?php

namespace Akyos\UXExportBundle;

use Akyos\UXExportBundle\DependencyInjection\Configuration;
use Akyos\UXExportBundle\DependencyInjection\UXExportBundleExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

class UXExportBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            ->scalarNode('path')
            ->defaultValue('%kernel.project_dir%/var/export/')
            ->info('This is the path parameter for your bundle.')
            ->end()
            ->end();
    }


    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');

        $container->parameters()->set('ux_export.path', $config['path']);
    }
}
