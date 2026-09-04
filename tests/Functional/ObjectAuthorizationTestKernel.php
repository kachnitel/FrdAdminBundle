<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\ObjectAuthVoter;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Component test kernel for object-authorization functional tests.
 *
 * Adds ObjectAuthVoter on top of everything ComponentTestKernel already
 * wires (LiveComponent bundle, routing, TestAdminEntityVoter for class-level
 * checks). No changes to ComponentTestKernel's own registrations are
 * needed: TestAdminEntityVoter's supports() requires a string subject
 * (matching the real AdminEntityVoter), so it correctly abstains on
 * ObjectAuthEntity instances, leaving ObjectAuthVoter as the sole voter
 * with an opinion on that object subject.
 *
 * getCacheDir() returns a unique path so this kernel compiles its own
 * container rather than reusing ComponentTestKernel's warm cache.
 */
class ObjectAuthorizationTestKernel extends ComponentTestKernel
{
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $container->register(ObjectAuthVoter::class)
            ->addTag('security.voter');
    }

    public function getCacheDir(): string
    {
        return ParatestCacheDir::resolve('cache-object-auth');
    }
}
