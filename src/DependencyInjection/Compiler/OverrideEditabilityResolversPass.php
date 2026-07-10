<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\DependencyInjection\Compiler;

use Kachnitel\AdminBundle\Editability\AdminColumnEditabilityResolver;
use Kachnitel\AdminBundle\Field\AdminEditabilityResolver;
use Kachnitel\DynamicFormBundle\Editability\FieldEditabilityResolverInterface;
use Kachnitel\EntityComponentsBundle\Components\Field\EditabilityResolverInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Forces this bundle's two editability-resolver overrides to win regardless
 * of registerBundles()/bundles.php order.
 *
 * kachnitel/dynamic-form-bundle and kachnitel/entity-components-bundle each
 * ship their own permissive default alias for an interface this bundle is
 * meant to override (see config/services.yaml). Bundle extensions
 * (loadExtension()/load()) all run as the first step of container
 * compilation, before any compiler pass including this one, so by the time
 * process() below runs, every sibling bundle's default alias has already
 * been set. Per Symfony's own DependencyInjection docs: "If you need to
 * manipulate the configuration loaded by an extension ... use a compiler
 * pass which works with the full container after the extensions have been
 * processed." That guarantee — not registerBundles() order — is what makes
 * re-asserting both aliases here deterministic no matter which bundle a
 * consuming app (or a Flex recipe) happens to register last.
 *
 * Only the two interfaces actually in play are handled here; add a new
 * setAlias() line if a future sibling bundle introduces a third permissive
 * default this bundle needs to override.
 *
 * @see https://symfony.com/doc/current/components/dependency_injection/compilation.html
 * @see https://symfony.com/doc/current/service_container/tags.html#register-the-pass-with-the-container
 */
final class OverrideEditabilityResolversPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->setAlias(EditabilityResolverInterface::class, AdminEditabilityResolver::class);
        $container->setAlias(FieldEditabilityResolverInterface::class, AdminColumnEditabilityResolver::class);
    }
}
