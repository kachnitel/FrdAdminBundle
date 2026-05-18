<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Service\PropertyFilterabilityService;
use Kachnitel\AdminBundle\Utils\ObjectHelper;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Runtime for generating admin URLs for related entities and collections.
 *
 * "Can this property be filtered?" logic is delegated entirely to
 * PropertyFilterabilityService, keeping this class as a thin coordinator
 * between routing, entity discovery, and filter-parameter building.
 */
class AdminEntityUrlRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly AdminRouteRuntime $adminRouteRuntime,
        private readonly ?EntityDiscoveryService $entityDiscovery = null,
        private readonly ?EntityManagerInterface $em = null,
        private readonly ?PropertyFilterabilityService $filterabilityService = null,
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
     * Get a URL to the target entity's admin list, pre-filtered to show items
     * related to the given entity's collection property.
     *
     * For OneToMany associations the URL includes a columnFilter on the inverse
     * ManyToOne field. For ManyToMany the URL links without a pre-applied filter.
     *
     * Returns null when the target entity has no #[Admin] attribute.
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

        if (!$this->entityDiscovery->isAdminEntity($targetClass)) {
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

        $targetSlug = $this->toEntitySlug($targetShortName);
        $parameters = $this->buildCollectionParameters($entity, $metadata, $property, $targetClass, $targetSlug);

        return $this->router->generate($route, $parameters);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * @param ClassMetadata<object> $metadata
     * @param class-string $targetClass
     * @return array<string, mixed>
     */
    private function buildCollectionParameters(
        object $entity,
        ClassMetadata $metadata,
        string $property,
        string $targetClass,
        string $targetSlug,
    ): array {
        $parameters = ['entitySlug' => $targetSlug];

        if ($this->filterabilityService === null) {
            return $parameters;
        }

        $mapping     = $metadata->getAssociationMapping($property);
        $filterField = $mapping->mappedBy ?? $mapping->inversedBy ?? null;

        if ($filterField === null) {
            return $parameters;
        }

        $filterEntry = $this->filterabilityService->buildCollectionFilterEntry($entity, $filterField, $targetClass);

        if ($filterEntry !== null) {
            $parameters['columnFilters'] = $filterEntry;
        }

        return $parameters;
    }

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
            $parameters['columnFilters'] = ['id' => (string) $entityId];
        }

        return $this->router->generate($indexRoute, $parameters);
    }

    private function toEntitySlug(string $shortName): string
    {
        return strtolower((string) preg_replace('/[A-Z]/', '-$0', lcfirst($shortName)));
    }
}
