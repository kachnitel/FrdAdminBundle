<?php

namespace Frd\AdminBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Bundle extension for dependency injection configuration.
 */
class FrdAdminExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Store configuration as parameters
        $container->setParameter('frd_admin.entity_namespace', $config['entity_namespace']);
        $container->setParameter('frd_admin.form_namespace', $config['form_namespace']);
        $container->setParameter('frd_admin.form_suffix', $config['form_suffix']);
        $container->setParameter('frd_admin.route_prefix', $config['route_prefix']);
        $container->setParameter('frd_admin.dashboard_route', $config['dashboard_route']);
        $container->setParameter('frd_admin.required_role', $config['required_role']);
        $container->setParameter('frd_admin.entities', $config['entities'] ?? []);
        $container->setParameter('frd_admin.enable_generic_controller', $config['enable_generic_controller']);

        // Load services
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config')
        );
        $loader->load('services.yaml');

        // Register Twig paths
        // $container->prependExtensionConfig('twig', [
        //     'paths' => [
        //         dirname(__DIR__, 2) . '/templates' => 'FrdAdmin',
        //     ],
        // ]);
    }

    public function getAlias(): string
    {
        return 'frd_admin';
    }
}
