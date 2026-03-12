<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Test kernel that disables authentication (required_role: null).
 *
 * IMPORTANT: getCacheDir() must return a unique path so this kernel gets its own
 * compiled container. All TestKernel subclasses share the same sys_get_temp_dir()
 * base — without differentiation the parent's warm cache is reused and any
 * compiler passes or config overrides are silently skipped.
 */
class NoAuthTestKernel extends TestKernel
{
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->setParameter('kachnitel_admin.required_role', null);
            }
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/kachnitel-admin-bundle-test/cache-no-auth';
    }
}
