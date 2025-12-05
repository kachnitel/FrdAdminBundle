<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\Loader\LoaderInterface;

/**
 * Test kernel for component tests that registers a test voter to bypass authentication.
 * This allows testing component functionality without dealing with authentication complexity.
 */
class ComponentTestKernel extends TestKernel
{
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        // Register test voter that always grants access for component tests
        $container->register(TestAdminEntityVoter::class)
            ->addTag('security.voter', ['priority' => 1000]);
    }
}
