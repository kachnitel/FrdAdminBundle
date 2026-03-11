<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Test kernel for dashboard permission tests.
 * Adds http_basic to the main firewall so HTTP Basic credentials are accepted.
 *
 * getCacheDir() returns a unique path so this kernel compiles its own container
 * rather than reusing the parent TestKernel's warm cache.
 */
class DashboardPermissionTestKernel extends TestKernel
{
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $container->loadFromExtension('security', [
            'firewalls' => [
                'main' => [
                    'lazy'       => true,
                    'provider'   => 'test',
                    'http_basic' => ['realm' => 'Admin Test'],
                ],
            ],
        ]);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/kachnitel-admin-bundle-test/cache-dashboard-perm';
    }
}
