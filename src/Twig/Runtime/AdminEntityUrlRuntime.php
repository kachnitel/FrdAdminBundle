<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Utils\CollectionScopeParameter;
use Kachnitel\AdminBundle\Utils\ObjectHelper;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Runtime for generating admin URLs for related entities and collections.
 *
 * Collection links carry a CollectionScopeParameter (`?collection=Owner:id:property`)
 * rather than a column filter, so the index page lists exactly the rows the collection
 * dialog shows — CollectionScopeResolver authorizes and applies the same scope for both.
 */
class AdminEntityUrlRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly AdminRouteRuntime $adminRouteRuntime,
        private readonly ?EntityDiscoveryService $entityDiscovery = null,
        private readonly ?EntityManagerInterface $em = null,
    ) {}

    /**
     * Get a URL to the admin page for a single related entity.
     *
     * Tries the "show" route first, falls back to the "index" route with an id filter.
     * Returns null when the entity's class has no #[Admin] attribute.
     */
    public function getEntityAdminUrl(object $relatedEntity, bool $checkAccess = true): ?string
    {
        if ($this->entityDiscovery === null) {
            return null;
        }

        $entityClass = ObjectHelper::getRealClass($relatedEntity);

        if (!$this->entityDiscovery->isAdminEntity($entityClass)) {
            return null;
        }

        $shortName  = (new \ReflectionClass($entityClass))->getShortName();
        $entitySlug = $this->toEntitySlug($shortName);
        $entityId   = method_exists($relatedEntity, 'getId') ? $relatedEntity->getId() : null;

        return $this->tryShowUrl($entityClass, $shortName, $entitySlug, $entityId, $checkAccess)
            ?? $this->tryIndexUrl($entityClass, $shortName, $entitySlug, $entityId, $checkAccess);
    }

    /**
     * Get a URL to the target entity's admin list, scoped to the given entity's
     * collection property.
     *
     * Works for OneToMany and both ManyToMany variants. Returns null when the property
     * is not a collection, the owner or target has no #[Admin] attribute, the owner has
     * no id yet, or (with $checkAccess) the target's index is not accessible.
     */
    public function getCollectionAdminUrl(object $entity, string $property, bool $checkAccess = true): ?string
    {
        if ($this->em === null || $this->entityDiscovery === null) {
            return null;
        }

        $entityClass = ObjectHelper::getRealClass($entity);
        $metadata    = $this->em->getClassMetadata($entityClass);

        if (!$metadata->isCollectionValuedAssociation($property)) {
            return null;
        }

        /** @var class-string $targetClass */
        $targetClass = $metadata->getAssociationTargetClass($property);

        if (!$this->entityDiscovery->isAdminEntity($targetClass) || !$this->entityDiscovery->isAdminEntity($entityClass)) {
            return null;
        }

        $route = $this->adminRouteRuntime->getRoute($targetClass, 'index');
        if ($route === null) {
            return null;
        }

        $targetShortName = (new \ReflectionClass($targetClass))->getShortName();

        if ($checkAccess && !$this->adminRouteRuntime->isActionAccessible($targetShortName, 'index')) {
            return null;
        }

        $ownerId = method_exists($entity, 'getId') ? $entity->getId() : null;
        if (!is_int($ownerId) && !is_string($ownerId)) {
            return null;
        }

        return $this->router->generate($route, [
            'entitySlug'                        => $this->toEntitySlug($targetShortName),
            CollectionScopeParameter::QUERY_KEY => CollectionScopeParameter::encode($entityClass, $ownerId, $property),
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function tryShowUrl(
        string $entityClass,
        string $shortName,
        string $entitySlug,
        mixed $entityId,
        bool $checkAccess,
    ): ?string {
        if ($checkAccess && !$this->adminRouteRuntime->isActionAccessible($shortName, 'show')) {
            return null;
        }

        $showRoute = $this->adminRouteRuntime->getRoute($entityClass, 'show');
        if ($showRoute === null || $entityId === null) {
            return null;
        }

        return $this->router->generate($showRoute, [
            'entitySlug' => $entitySlug,
            'id'         => $entityId,
        ]);
    }

    private function tryIndexUrl(
        string $entityClass,
        string $shortName,
        string $entitySlug,
        mixed $entityId,
        bool $checkAccess,
    ): ?string {
        if ($checkAccess && !$this->adminRouteRuntime->isActionAccessible($shortName, 'index')) {
            return null;
        }

        $indexRoute = $this->adminRouteRuntime->getRoute($entityClass, 'index');
        if ($indexRoute === null) {
            return null;
        }

        $parameters = ['entitySlug' => $entitySlug];
        if ($entityId !== null) {
            $parameters['columnFilters'] = [
                'id' => is_scalar($entityId) ? (string) $entityId : '',
            ];
        }

        return $this->router->generate($indexRoute, $parameters);
    }

    private function toEntitySlug(string $shortName): string
    {
        return strtolower((string) preg_replace('/[A-Z]/', '-$0', lcfirst($shortName)));
    }
}
