<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DependencyInjection\Compiler;

use Kachnitel\AdminBundle\DependencyInjection\Compiler\OverrideTypeGuesserPass;
use Kachnitel\DynamicFormBundle\Form\DoctrineFormTypeMapper;
use Kachnitel\DynamicFormBundle\Form\TypeGuessing\ConventionalFieldTypeGuesser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\FormTypeGuesserChain;

/**
 * Verifies that OverrideTypeGuesserPass correctly wires a FormTypeGuesserChain
 * (combining Symfony's validator guesser with ConventionalFieldTypeGuesser) into
 * DoctrineFormTypeMapper's $typeGuesser argument, so that naming-convention type
 * guessing (password → PasswordType, email → EmailType, …) is active for all
 * admin-bundle users by default — without any extra configuration.
 *
 * Uses a real ContainerBuilder (not a mock) — the standard Symfony approach for
 * testing compiler passes.
 *
 * @group type-guessing
 * @group auto-form
 */
#[CoversClass(OverrideTypeGuesserPass::class)]
#[Group('type-guessing')]
#[Group('auto-form')]
final class OverrideTypeGuesserPassTest extends TestCase
{
    // ── No-op guard ───────────────────────────────────────────────────────────

    #[Test]
    public function processIsANoOpWhenDoctrineFormTypeMapperIsNotRegistered(): void
    {
        $container = new ContainerBuilder();
        $pass = new OverrideTypeGuesserPass();

        $pass->process($container);

        $this->assertFalse(
            $container->hasDefinition('kachnitel_admin_bundle.form_type_guesser_chain'),
            'No chain service should be registered when DoctrineFormTypeMapper is absent.'
        );
    }

    // ── Chain service registration ────────────────────────────────────────────

    #[Test]
    public function processCreatesGuesserChainService(): void
    {
        $container = $this->containerWithMapper();

        (new OverrideTypeGuesserPass())->process($container);

        $this->assertTrue(
            $container->hasDefinition('kachnitel_admin_bundle.form_type_guesser_chain'),
            'The guesser chain service must be registered after the pass runs.'
        );
    }

    #[Test]
    public function guesserChainServiceHasCorrectClass(): void
    {
        $container = $this->containerWithMapper();

        (new OverrideTypeGuesserPass())->process($container);

        $this->assertSame(
            FormTypeGuesserChain::class,
            $container->getDefinition('kachnitel_admin_bundle.form_type_guesser_chain')->getClass()
        );
    }

    #[Test]
    public function guesserChainIncludesValidatorGuesserReference(): void
    {
        $container = $this->containerWithMapper();

        (new OverrideTypeGuesserPass())->process($container);

        $guesserIds = $this->chainGuesserIds($container);
        $this->assertContains(
            'form.type_guesser.validator',
            $guesserIds,
            'The chain must include Symfony\'s constraint-driven validator guesser.'
        );
    }

    #[Test]
    public function guesserChainIncludesConventionalFieldTypeGuesserReference(): void
    {
        $container = $this->containerWithMapper();

        (new OverrideTypeGuesserPass())->process($container);

        $guesserIds = $this->chainGuesserIds($container);
        $this->assertContains(
            ConventionalFieldTypeGuesser::class,
            $guesserIds,
            'The chain must include the naming-convention guesser (password/email/tel/…).'
        );
    }

    // ── Argument override on DoctrineFormTypeMapper ───────────────────────────

    #[Test]
    public function typeGuesserArgumentIsSetOnDoctrineFormTypeMapper(): void
    {
        $container = $this->containerWithMapper();

        (new OverrideTypeGuesserPass())->process($container);

        $arg = $container->getDefinition(DoctrineFormTypeMapper::class)->getArgument('$typeGuesser');
        $this->assertInstanceOf(Reference::class, $arg);
        $this->assertSame(
            'kachnitel_admin_bundle.form_type_guesser_chain',
            (string) $arg,
            'The $typeGuesser argument must point to the newly-registered chain service.'
        );
    }

    #[Test]
    public function processOverridesAPreExistingTypeGuesserArgument(): void
    {
        // Simulates the state after dynamic-form-bundle's own services.yaml runs:
        // it already wires form.type_guesser.validator as $typeGuesser.
        // Our pass must replace that single guesser with the full chain.
        $container = $this->containerWithMapper();
        $container->getDefinition(DoctrineFormTypeMapper::class)
            ->setArgument('$typeGuesser', new Reference('form.type_guesser.validator'));

        (new OverrideTypeGuesserPass())->process($container);

        $arg = $container->getDefinition(DoctrineFormTypeMapper::class)->getArgument('$typeGuesser');
        $this->assertSame('kachnitel_admin_bundle.form_type_guesser_chain', (string) $arg);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * A ContainerBuilder that mimics the minimal state present after all bundle
     * extensions have loaded: DoctrineFormTypeMapper, ConventionalFieldTypeGuesser,
     * and a stub for form.type_guesser.validator are all registered.
     */
    private function containerWithMapper(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(DoctrineFormTypeMapper::class, DoctrineFormTypeMapper::class);
        $container->register(ConventionalFieldTypeGuesser::class, ConventionalFieldTypeGuesser::class);
        $container->register('form.type_guesser.validator', \stdClass::class);

        return $container;
    }

    /**
     * @return list<string>
     */
    private function chainGuesserIds(ContainerBuilder $container): array
    {
        /** @var list<Reference> $refs */
        $refs = $container->getDefinition('kachnitel_admin_bundle.form_type_guesser_chain')->getArgument(0);

        return array_map(static fn (Reference $ref): string => (string) $ref, $refs);
    }
}
