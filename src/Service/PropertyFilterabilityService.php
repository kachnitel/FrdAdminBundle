<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Kachnitel\AdminBundle\Attribute\ColumnFilter;

/**
 * Answers all "can this property be filtered?" questions for admin URL generation
 * and other consumers that need to reason about column filterability.
 *
 * Wraps FilterMetadataProvider and centralises the ColumnFilter attribute
 * reflection that was previously scattered across AdminEntityUrlRuntime.
 *
 * Consumers (e.g. AdminEntityUrlRuntime) delegate to this service rather than
 * depending directly on FilterMetadataProvider and PHP reflection, keeping their
 * coupling count within PHPMD thresholds.
 */
class PropertyFilterabilityService
{
    public function __construct(
        private readonly FilterMetadataProvider $filterMetadataProvider,
        private readonly bool $debug = false,
    ) {}

    /**
     * Get the filter metadata array for a property, or null when the property
     * is not filterable (not a Doctrine field/association, not enabled, etc.).
     *
     * Delegates directly to FilterMetadataProvider::getFilterForProperty().
     *
     * @param class-string $entityClass
     * @return array<string, mixed>|null
     */
    public function getFilterConfig(string $entityClass, string $property): ?array
    {
        return $this->filterMetadataProvider->getFilterForProperty($entityClass, $property);
    }

    /**
     * Whether the property can be filtered (i.e. getFilterConfig() returns non-null).
     *
     * @param class-string $entityClass
     */
    public function isFilterable(string $entityClass, string $property): bool
    {
        return $this->getFilterConfig($entityClass, $property) !== null;
    }

    /**
     * Whether the property carries an explicit #[ColumnFilter(enabled: false)].
     *
     * A return value of true means the developer actively opted out, which is a
     * configuration mistake when the property is used as a collection link target.
     * Used by buildCollectionFilterEntry() to produce a helpful debug-mode error.
     *
     * @param class-string $entityClass
     */
    public function isExplicitlyDisabled(string $entityClass, string $property): bool
    {
        try {
            $reflection = new \ReflectionClass($entityClass);
        } catch (\ReflectionException) { // @phpstan-ignore catch.neverThrown
            return false;
        }

        if (!$reflection->hasProperty($property)) {
            return false;
        }

        $attributes = $reflection->getProperty($property)->getAttributes(ColumnFilter::class);

        if (empty($attributes)) {
            return false;
        }

        /** @var ColumnFilter $filter */
        $filter = $attributes[0]->newInstance();

        return !$filter->enabled;
    }

    /**
     * Build the `['field' => 'value']` fragment for a collection admin URL filter,
     * or return null when no filter should be applied.
     *
     * Returns null when:
     *   - the entity has no getId() method or its ID is null
     *   - the property is not filterable on the target entity
     *
     * In debug mode, throws a LogicException when the property exists on the target
     * entity but is explicitly disabled via #[ColumnFilter(enabled: false)] — that
     * is a clear configuration mistake that should surface early.
     *
     * @param class-string $targetClass
     * @return array<string, string>|null  e.g. ['product' => '42']
     */
    public function buildCollectionFilterEntry(
        object $entity,
        string $filterField,
        string $targetClass,
    ): ?array {
        if (!method_exists($entity, 'getId') || $entity->getId() === null) {
            return null;
        }

        $config = $this->getFilterConfig($targetClass, $filterField);

        if ($config === null) {
            if ($this->debug && $this->isExplicitlyDisabled($targetClass, $filterField)) {
                throw new \LogicException(sprintf(
                    'A collection link targets "%s::$%s" as a filter field, '
                    . 'but that property has #[ColumnFilter(enabled: false)]. '
                    . 'Remove enabled: false or add a filterable #[ColumnFilter] attribute.',
                    $targetClass,
                    $filterField,
                ));
            }

            return null;
        }

        return [$filterField => (string) $entity->getId()];
    }
}
