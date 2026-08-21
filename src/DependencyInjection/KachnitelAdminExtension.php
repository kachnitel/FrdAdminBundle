<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Bundle extension for dependency injection configuration.
 */
class KachnitelAdminExtension extends Extension
{
    /**
     * @param list<array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        /** @var array{
         *     entity_namespace: string,
         *     form_namespace: string,
         *     form_suffix: string,
         *     route_prefix: string,
         *     dashboard_route: string,
         *     required_role: string|null,
         *     base_layout: string|null,
         *     enable_generic_controller: bool,
         *     pagination: array{default_items_per_page: int, allowed_items_per_page: list<int>},
         *     theme: string
         * } $config
         */
        $config = $this->processConfiguration($configuration, $configs);

        // Store configuration as parameters
        $container->setParameter('kachnitel_admin.entity_namespace', $config['entity_namespace']);
        $container->setParameter('kachnitel_admin.form_namespace', $config['form_namespace']);
        $container->setParameter('kachnitel_admin.form_suffix', $config['form_suffix']);
        $container->setParameter('kachnitel_admin.route_prefix', $config['route_prefix']);
        $container->setParameter('kachnitel_admin.dashboard_route', $config['dashboard_route']);
        $container->setParameter('kachnitel_admin.required_role', $config['required_role']);
        $container->setParameter('kachnitel_admin.base_layout', $config['base_layout']);
        $container->setParameter('kachnitel_admin.enable_generic_controller', $config['enable_generic_controller']);
        $container->setParameter('kachnitel_admin.pagination.default_items_per_page', $config['pagination']['default_items_per_page']);
        $container->setParameter('kachnitel_admin.pagination.allowed_items_per_page', $config['pagination']['allowed_items_per_page']);
        $container->setParameter('kachnitel_admin.theme', $config['theme']);

        // Load services
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config')
        );
        $loader->load('services.yaml');
    }

    public function getAlias(): string
    {
        return 'kachnitel_admin';
    }
}
