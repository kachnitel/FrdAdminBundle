<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DependencyInjection\Compiler;

use Kachnitel\AdminBundle\DependencyInjection\Compiler\OverrideEditabilityResolversPass;
use Kachnitel\AdminBundle\Editability\AdminColumnEditabilityResolver;
use Kachnitel\AdminBundle\Field\AdminEditabilityResolver;
use Kachnitel\DynamicFormBundle\Editability\AlwaysEditableFieldResolver;
use Kachnitel\DynamicFormBundle\Editability\FieldEditabilityResolverInterface;
use Kachnitel\EntityComponentsBundle\Components\Field\DefaultEditabilityResolver;
use Kachnitel\EntityComponentsBundle\Components\Field\EditabilityResolverInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Fast, kernel-free companion to tests/Functional/EditabilityResolverWiringTest.php.
 *
 * That functional test proves the *outcome* is correct across the whole
 * booted kernel (all three bundles registered); this one proves the *pass
 * itself* does the overwrite, in isolation, on a bare ContainerBuilder —
 * no kernel boot, so it runs in milliseconds and pinpoints a regression to
 * this class specifically if it ever fails while the functional test still
 * passes (or vice versa).
 *
 * Kachnitel\EntityComponentsBundle\Components\Field\DefaultEditabilityResolver
 * is referenced by name only, taken from that bundle's own docblocks
 * (mirrored in EditabilityResolverWiringTest) — adjust the import if the
 * actual class name differs.
 *
 * @group editability
 */
#[CoversClass(OverrideEditabilityResolversPass::class)]
final class OverrideEditabilityResolversPassTest extends TestCase
{
    #[Test]
    public function overridesAnAlreadySetAliasFromDynamicFormBundle(): void
    {
        $container = new ContainerBuilder();
        $container->register(AdminColumnEditabilityResolver::class);
        $container->register(AlwaysEditableFieldResolver::class);
        // Simulates dynamic-form-bundle's own extension having already run
        // and won the alias, e.g. because it was registered last.
        $container->setAlias(FieldEditabilityResolverInterface::class, AlwaysEditableFieldResolver::class);

        (new OverrideEditabilityResolversPass())->process($container);

        $this->assertSame(
            AdminColumnEditabilityResolver::class,
            (string) $container->getAlias(FieldEditabilityResolverInterface::class),
        );
    }

    #[Test]
    public function overridesAnAlreadySetAliasFromEntityComponentsBundle(): void
    {
        $container = new ContainerBuilder();
        $container->register(AdminEditabilityResolver::class);
        $container->register(DefaultEditabilityResolver::class);
        $container->setAlias(EditabilityResolverInterface::class, DefaultEditabilityResolver::class);

        (new OverrideEditabilityResolversPass())->process($container);

        $this->assertSame(
            AdminEditabilityResolver::class,
            (string) $container->getAlias(EditabilityResolverInterface::class),
        );
    }

    #[Test]
    public function setsBothAliasesFromScratchWhenNeitherExistedYet(): void
    {
        $container = new ContainerBuilder();
        $container->register(AdminColumnEditabilityResolver::class);
        $container->register(AdminEditabilityResolver::class);

        (new OverrideEditabilityResolversPass())->process($container);

        $this->assertSame(
            AdminColumnEditabilityResolver::class,
            (string) $container->getAlias(FieldEditabilityResolverInterface::class),
        );
        $this->assertSame(
            AdminEditabilityResolver::class,
            (string) $container->getAlias(EditabilityResolverInterface::class),
        );
    }
}
