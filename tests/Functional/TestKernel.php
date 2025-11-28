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
use Symfony\UX\TwigComponent\TwigComponentBundle;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new DoctrineBundle(),
            new SecurityBundle(),
            new TwigComponentBundle(),
            new LiveComponentBundle(),
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
        ]);

        $container->loadFromExtension('twig', [
            'default_path' => '%kernel.project_dir%/templates',
        ]);

        $container->loadFromExtension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'url' => 'sqlite:///:memory:',
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
                        'prefix' => 'Frd\AdminBundle\Tests\Fixtures',
                    ],
                ],
            ],
        ]);

        $container->loadFromExtension('security', [
            'password_hashers' => [
                'Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface' => 'plaintext',
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
                'Frd\\AdminBundle\\Twig\\Components\\' => 'components/',
            ],
        ]);
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
