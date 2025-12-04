<?php

declare(strict_types=1);

namespace Frd\AdminBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Frd\AdminBundle\Attribute\Admin;

/**
 * Discovers entities with the #[Admin] attribute.
 *
 * This service scans all Doctrine-managed entities and finds those
 * marked with the #[Admin] attribute, enabling auto-discovery of
 * admin-managed entities without YAML configuration.
 */
class EntityDiscoveryService
{
    /** @var array<string, Admin>|null Cached map of entity class => Admin attribute */
    private ?array $adminEntities = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * Get all entities with the #[Admin] attribute.
     *
     * @return array<string, Admin> Map of entity class name => Admin attribute
     */
    public function getAdminEntities(): array
    {
        if ($this->adminEntities !== null) {
            return $this->adminEntities;
        }

        $this->adminEntities = [];

        // Get all Doctrine-managed entity metadata
        $metadataFactory = $this->entityManager->getMetadataFactory();
        $allMetadata = $metadataFactory->getAllMetadata();

        foreach ($allMetadata as $metadata) {
            /** @var ClassMetadata<object> $metadata */
            $className = $metadata->getName();

            // Check if entity has #[Admin] attribute
            try {
                $reflectionClass = new \ReflectionClass($className);
                $attributes = $reflectionClass->getAttributes(Admin::class);

                if (count($attributes) > 0) {
                    /** @var Admin $adminAttribute */
                    $adminAttribute = $attributes[0]->newInstance();
                    $this->adminEntities[$className] = $adminAttribute;
                }
            } catch (\ReflectionException) {
                // Skip entities that can't be reflected
                continue;
            }
        }

        return $this->adminEntities;
    }

    /**
     * Check if an entity has the #[Admin] attribute.
     */
    public function isAdminEntity(string $className): bool
    {
        return isset($this->getAdminEntities()[$className]);
    }

    /**
     * Get the #[Admin] attribute for a specific entity.
     *
     * @return Admin|null The Admin attribute, or null if not found
     */
    public function getAdminAttribute(string $className): ?Admin
    {
        return $this->getAdminEntities()[$className] ?? null;
    }

    /**
     * Get list of entity class names that have #[Admin] attribute.
     *
     * @return array<string> List of fully-qualified class names
     */
    public function getAdminEntityClasses(): array
    {
        return array_keys($this->getAdminEntities());
    }

    /**
     * Get list of entity short names (without namespace) that have #[Admin].
     *
     * @return array<string> List of short class names
     */
    public function getAdminEntityShortNames(): array
    {
        $shortNames = [];

        foreach ($this->getAdminEntityClasses() as $className) {
            $parts = explode('\\', $className);
            $shortNames[] = end($parts);
        }

        return $shortNames;
    }

    /**
     * Resolve entity class name from short name.
     *
     * @param string $shortName Short entity name (e.g., 'Product')
     * @param string $defaultNamespace Fallback namespace if not found via attributes
     * @return string|null Fully-qualified class name, or null if not found
     */
    public function resolveEntityClass(string $shortName, string $defaultNamespace = 'App\\Entity\\'): ?string
    {
        // First, check admin entities
        foreach ($this->getAdminEntityClasses() as $className) {
            if (str_ends_with($className, '\\' . $shortName)) {
                return $className;
            }
        }

        // Fallback to default namespace
        $fallbackClass = $defaultNamespace . $shortName;
        if (class_exists($fallbackClass)) {
            return $fallbackClass;
        }

        return null;
    }

    /**
     * Clear the cached admin entities.
     *
     * Useful for testing or when entities are added dynamically.
     */
    public function clearCache(): void
    {
        $this->adminEntities = null;
    }
}
