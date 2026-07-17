<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\DependencyInjection\Compiler\OverrideEditabilityResolversPass;
use Kachnitel\AdminBundle\KachnitelAdminBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Regression test for the `required_role: false` normalization gap.
 *
 * KachnitelAdminBundle extends AbstractBundle and defines loadExtension(). Symfony's
 * AbstractBundle::getContainerExtension() is `final`, and when a bundle defines
 * loadExtension() it always synthesizes its own internal extension from configure()/
 * loadExtension() — it never falls back to scanning for a conventionally-named
 * {Bundle}Extension class. That means the separate, classic
 * DependencyInjection\Configuration + DependencyInjection\KachnitelAdminExtension pair
 * (alias 'kachnitel_admin', same as this bundle's own live extension) is never
 * instantiated by the kernel: it is dead code, unreachable through any registration path
 * this bundle documents (README only ever tells consumers to register KachnitelAdminBundle
 * itself). Only that dead Configuration class normalizes `required_role: false` to `null`.
 *
 * This test boots the bundle through the real, live registration path — the only one
 * consuming applications actually use — and asserts the resulting container parameter is
 * `null`, not the literal boolean `false`. Before the fix to
 * KachnitelAdminBundle::configure(), a scalarNode accepts a raw bool as-is, so the
 * parameter ends up being the boolean `false`. AdminEntityVoter::hasRole() is typed
 * `string $role` under `declare(strict_types=1)`, so the first vote that falls through to
 * the entity-level default (no #[Admin(permissions:)] override) throws a TypeError instead
 * of the documented "authentication disabled" behaviour.
 */
#[CoversClass(KachnitelAdminBundle::class)]
#[UsesClass(OverrideEditabilityResolversPass::class)]
#[Group('dependency-injection')]
#[Group('bundle-config')]
final class RequiredRoleFalseNormalizationTest extends TestCase
{
    public function testRequiredRoleFalseNormalizesToNullThroughTheLiveConfigPath(): void
    {
        $kernel = new class ('test', true) extends TestKernel {
            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                parent::registerContainerConfiguration($loader);

                $loader->load(static function (ContainerBuilder $container): void {
                    $container->loadFromExtension('framework', [
                        'test' => true,
                        'secret' => 'test',
                        'http_method_override' => false,
                        'router' => ['utf8' => true, 'resource' => 'default'],
                    ]);
                    $container->loadFromExtension('kachnitel_admin', [
                        // The value under test: the documented "disable authentication" sentinel.
                        'required_role' => false,
                    ]);
                });
            }
        };

        $kernel->boot();
        $container = $kernel->getContainer();

        self::assertTrue(
            $container->hasParameter('kachnitel_admin.required_role'),
            'kachnitel_admin.required_role parameter was not set at all — check the live '
            . 'extension actually ran.',
        );

        self::assertNull(
            $container->getParameter('kachnitel_admin.required_role'),
            'required_role: false must normalize to null (which AdminEntityVoter reads as '
            . '"authentication disabled"), not the literal boolean false — a raw false is '
            . 'later passed to code typed ?string/string and breaks AdminEntityVoter::hasRole().',
        );

        $kernel->shutdown();
    }
}
