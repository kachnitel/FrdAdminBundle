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
