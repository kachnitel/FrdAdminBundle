<?php

namespace Frd\AdminBundle\Tests\Functional;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Frd\AdminBundle\FrdAdminBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\UX\LiveComponent\LiveComponentBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Symfony\WebpackEncoreBundle\WebpackEncoreBundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            // Core Symfony Bundles
            new FrameworkBundle(),
            new TwigBundle(),
            new DoctrineBundle(),
            new SecurityBundle(),

            // Symfony UX Dependencies
            new TwigComponentBundle(),
            new LiveComponentBundle(),
            new StimulusBundle(),
            new WebpackEncoreBundle(),

            new FrdAdminBundle(),
        ];
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'test-secret',
            'test' => true,
            'router' => ['utf8' => true],
            'http_method_override' => false,
            'assets' => ['enabled' => true],
        ]);

        $container->loadFromExtension('twig', [
            'default_path' => '%kernel.project_dir%/templates',
        ]);

        $container->loadFromExtension('webpack_encore', [
            'output_path' => '%kernel.project_dir%/public/build',
        ]);

        $container->loadFromExtension('doctrine', [
            'dbal' => [
                // FIX: Use a file-based DB to persist across kernel reboots/connection closures
                'url' => 'sqlite:///%kernel.cache_dir%/test.db',
            ],
            'orm' => [
                'auto_generate_proxy_classes' => true,
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                'auto_mapping' => true,
                'mappings' => [
                    'FrdAdminBundleTests' => [
                        'is_bundle' => false,
                        'type' => 'attribute',
                        'dir' => '%kernel.project_dir%/tests/Fixtures',
                        'prefix' => 'Frd\\AdminBundle\\Tests\\Fixtures',
                    ],
                ],
            ],
        ]);

        $container->loadFromExtension('security', [
            'password_hashers' => [
                'Symfony\\Component\\Security\\Core\\User\\PasswordAuthenticatedUserInterface' => 'plaintext',
            ],
            'providers' => [
                'test' => ['memory' => null],
            ],
            'firewalls' => [
                'dev' => ['pattern' => '^/(_(profiler|wdt)|css|images|js)/', 'security' => false],
                'main' => ['lazy' => true],
            ],
        ]);

        $container->loadFromExtension('twig_component', [
            'anonymous_template_directory' => 'components/',
            'defaults' => [
                'Frd\\\\AdminBundle\\\\Twig\\\\Components\\\\' => 'components/',
            ],
        ]);

        $container->loadFromExtension('frd_admin', [
            'entity_namespace' => 'Frd\\AdminBundle\\Tests\\Fixtures\\',
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/../../vendor/symfony/ux-live-component/config/routes.php')
            ->prefix('/_components');
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/frd-admin-bundle-test/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/frd-admin-bundle-test/logs';
    }
}
