<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Proxy;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Runtime for generating admin URLs for related entities and collections.
 */
class AdminEntityUrlRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private RouterInterface $router,
        private AdminRouteRuntime $adminRouteRuntime,
        private ?EntityDiscoveryService $entityDiscovery = null,
        private ?EntityManagerInterface $em = null,
    ) {}

    /**
     * Get a URL to the admin page for a single related entity.
     *
     * Tries the "show" route first (direct link to the entity's detail page).
     * Falls back to the "index" route with an id filter if no show route is available.
     *
     * Returns null if the entity's class has no #[Admin] attribute.
     */
    public function getEntityAdminUrl(object $relatedEntity, bool $checkAccess = true): ?string
    {
        if ($this->entityDiscovery === null) {
            return null;
        }

        $entityClass = $this->getRealClass($relatedEntity);

        if (!$this->entityDiscovery->isAdminEntity($entityClass)) {
            return null;
        }

        $shortName = (new \ReflectionClass($entityClass))->getShortName();
        $entitySlug = $this->toEntitySlug($shortName);
        $entityId = method_exists($relatedEntity, 'getId') ? $relatedEntity->getId() : null;

        return $this->tryShowUrl($entityClass, $shortName, $entitySlug, $entityId, $checkAccess)
            ?? $this->tryIndexUrl($entityClass, $shortName, $entitySlug, $entityId, $checkAccess);
    }

    /**
     * Get a URL to the target entity's admin list, pre-filtered to show items
     * related to the given entity's collection property.
     *
     * For OneToMany associations, the URL includes a columnFilter on the inverse
     * ManyToOne field so the list shows only items belonging to this entity.
     * For ManyToMany, the URL links to the target admin without a pre-applied filter.
     *
     * Returns null if the target entity has no #[Admin] attribute.
     */
    public function getCollectionAdminUrl(object $entity, string $property, bool $checkAccess = true): ?string
    {
        $target = $this->resolveCollectionTarget($entity, $property);
        if ($target === null) {
            return null;
        }

        $targetClass = $target['targetClass'];
        $route = $this->adminRouteRuntime->getRoute($targetClass, 'index');
        if ($route === null) {
            return null;
        }

        $targetShortName = (new \ReflectionClass($targetClass))->getShortName();

        if ($checkAccess && !$this->adminRouteRuntime->isActionAccessible($targetShortName, 'index')) {
            return null;
        }

        $targetSlug = $this->toEntitySlug($targetShortName);
        $parameters = $this->buildCollectionParameters($targetSlug, $entity, $target['metadata'], $property);

        return $this->router->generate($route, $parameters);
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
            'id' => $entityId,
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

    /**
     * Resolve the target class and metadata for a collection-valued association.
     *
     * @return array{targetClass: class-string, metadata: ClassMetadata<object>}|null
     */
    private function resolveCollectionTarget(object $entity, string $property): ?array
    {
        if ($this->em === null || $this->entityDiscovery === null) {
            return null;
        }

        $entityClass = $this->getRealClass($entity);
        $metadata = $this->em->getClassMetadata($entityClass);

        if (!$metadata->isCollectionValuedAssociation($property)) {
            return null;
        }

        $targetClass = $metadata->getAssociationTargetClass($property);

        if (!$this->entityDiscovery->isAdminEntity($targetClass)) {
            return null;
        }

        return ['targetClass' => $targetClass, 'metadata' => $metadata];
    }

    /**
     * Build route parameters for a collection admin URL.
     *
     * @param ClassMetadata<object> $metadata
     * @return array<string, mixed>
     */
    private function buildCollectionParameters(
        string $targetSlug,
        object $entity,
        ClassMetadata $metadata,
        string $property,
    ): array {
        $mapping = $metadata->getAssociationMapping($property);
        $mappedBy = $mapping->mappedBy ?? null;

        $parameters = ['entitySlug' => $targetSlug];

        if ($mappedBy !== null && method_exists($entity, 'getId') && $entity->getId() !== null) {
            $parameters['columnFilters'] = [$mappedBy => (string) $entity->getId()];
        }

        return $parameters;
    }

    /**
     * Get the real class name of an object, handling Doctrine proxies.
     */
    private function getRealClass(object $object): string
    {
        if ($object instanceof Proxy) {
            return get_parent_class($object);
        }

        return get_class($object);
    }

    /**
     * Convert PascalCase short name to kebab-case entity slug.
     */
    private function toEntitySlug(string $shortName): string
    {
        return strtolower((string) preg_replace('/[A-Z]/', '-$0', lcfirst($shortName)));
    }
}
