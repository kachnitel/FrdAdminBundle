<?php

namespace Frd\AdminBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class FrdAdminBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Store configuration as parameters
        $builder->setParameter('frd_admin.entity_namespace', $config['entity_namespace']);
        $builder->setParameter('frd_admin.form_namespace', $config['form_namespace']);
        $builder->setParameter('frd_admin.form_suffix', $config['form_suffix']);
        $builder->setParameter('frd_admin.route_prefix', $config['route_prefix']);
        $builder->setParameter('frd_admin.dashboard_route', $config['dashboard_route']);
        $builder->setParameter('frd_admin.required_role', $config['required_role']);
        $builder->setParameter('frd_admin.entities', $config['entities'] ?? []);
        $builder->setParameter('frd_admin.enable_generic_controller', $config['enable_generic_controller']);

        // Load services
        $container->import('../config/services.yaml');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Register Twig namespace
        $container->extension('twig', [
            'paths' => [
                $this->getPath() . '/templates' => 'FrdAdmin',
            ],
        ]);

        // Register LiveComponent namespace
        $container->extension('twig_component', [
            'defaults' => [
                'Frd\\AdminBundle\\Twig\\Components\\' => 'components/',
            ],
        ]);
    }
}
