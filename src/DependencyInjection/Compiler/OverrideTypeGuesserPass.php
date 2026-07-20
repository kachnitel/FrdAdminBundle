<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DependencyInjection\Compiler;

use Kachnitel\DynamicFormBundle\Form\DoctrineFormTypeMapper;
use Kachnitel\DynamicFormBundle\Form\TypeGuessing\ConventionalFieldTypeGuesser;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\FormTypeGuesserChain;

/**
 * Opts admin-bundle users into naming-convention type guessing by default.
 *
 * dynamic-form-bundle ships ConventionalFieldTypeGuesser but leaves it off
 * by default — a naming convention alone shouldn't be forced on every consumer
 * of the library. admin-bundle is the bundle that opts in on their behalf,
 * the same way OverrideEditabilityResolversPass already wins the
 * FieldEditabilityResolverInterface alias race regardless of bundle
 * registration order.
 *
 * What this pass does:
 *   1. Guards: if DoctrineFormTypeMapper isn't registered (dynamic-form-bundle
 *      absent), does nothing — the container still compiles cleanly.
 *   2. Registers a FormTypeGuesserChain that combines:
 *        - form.type_guesser.validator (Symfony's constraint-driven guesser —
 *          already wired by dynamic-form-bundle as a single $typeGuesser)
 *        - ConventionalFieldTypeGuesser (naming: password/email/tel/url/color)
 *   3. Overrides DoctrineFormTypeMapper's $typeGuesser argument with the chain.
 *
 * Result: any string field named `password`, `email`, `phone`, `websiteUrl`,
 * `themeColor`, etc. gets the right Symfony form widget automatically, with
 * zero developer configuration required.
 *
 * Running as a compiler pass (vs. a plain services.yaml alias) is essential:
 * dynamic-form-bundle's own extension also sets $typeGuesser in its
 * services.yaml, so a plain override in services.yaml would lose a load-order
 * race. Compiler passes run after all extensions and always win.
 */
final class OverrideTypeGuesserPass implements CompilerPassInterface
{
    private const MAPPER_SERVICE_ID = DoctrineFormTypeMapper::class;
    private const CHAIN_SERVICE_ID  = 'kachnitel_admin_bundle.form_type_guesser_chain';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::MAPPER_SERVICE_ID)) {
            // dynamic-form-bundle not registered — nothing to wire.
            return;
        }

        $container->register(self::CHAIN_SERVICE_ID, FormTypeGuesserChain::class)
            ->setArguments([[
                new Reference('form.type_guesser.validator'),
                new Reference(ConventionalFieldTypeGuesser::class),
            ]]);

        $container->getDefinition(self::MAPPER_SERVICE_ID)
            ->setArgument('$typeGuesser', new Reference(self::CHAIN_SERVICE_ID));
    }
}
