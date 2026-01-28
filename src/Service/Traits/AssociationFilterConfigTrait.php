<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service\Traits;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;
use ReflectionClass;

/**
 * Handles filter configuration for associations (relations and collections).
 */
trait AssociationFilterConfigTrait
{
    /**
     * Default searchable fields for common entity types.
     */
    private const DEFAULT_SEARCH_FIELDS = [
        'User' => ['name', 'email', 'firstName', 'lastName'],
        'Product' => ['name', 'sku', 'description'],
        'Order' => ['id', 'orderNumber', 'externalId'],
        'Customer' => ['name', 'email', 'phone', 'companyName'],
    ];

    /**
     * Fields to check for display value, in priority order.
     */
    private const DISPLAY_FIELD_PRIORITY = ['name', 'label', 'title'];

    /**
     * Get the entity manager instance.
     */
    abstract protected function getEntityManager(): EntityManagerInterface;

    /**
     * Get filter config for an association (relation or collection).
     *
     * @param ClassMetadata<object> $metadata
     * @return array<string, mixed>
     */
    private function getAssociationFilterConfig(
        ClassMetadata $metadata,
        string $associationName,
        ?ColumnFilter $attribute
    ): array {
        $isCollection = $metadata->isCollectionValuedAssociation($associationName);
        $target = $this->resolveAssociationTarget($metadata, $associationName);
        $searchFields = $this->resolveSearchFields($target['metadata'], $target['entityName'], $attribute);

        $config = [
            'type' => $isCollection ? ColumnFilter::TYPE_COLLECTION : ColumnFilter::TYPE_RELATION,
            'targetClass' => $target['class'],
            'targetEntity' => $target['entityName'],
            'searchFields' => $searchFields,
            'operator' => 'LIKE',
        ];

        if ($isCollection) {
            $config['excludeFromGlobalSearch'] = $attribute === null || $attribute->excludeFromGlobalSearch;
        }

        return $config;
    }

    /**
     * Resolve target class information for an association.
     *
     * @param ClassMetadata<object> $metadata
     * @return array{class: class-string, entityName: string, metadata: ClassMetadata<object>}
     */
    private function resolveAssociationTarget(ClassMetadata $metadata, string $associationName): array
    {
        $targetClass = $metadata->getAssociationTargetClass($associationName);

        return [
            'class' => $targetClass,
            'entityName' => (new ReflectionClass($targetClass))->getShortName(),
            'metadata' => $this->getEntityManager()->getClassMetadata($targetClass),
        ];
    }

    /**
     * Resolve and validate search fields for an association target.
     *
     * @param ClassMetadata<object> $targetMetadata
     * @return list<string>
     */
    private function resolveSearchFields(
        ClassMetadata $targetMetadata,
        string $targetEntityName,
        ?ColumnFilter $attribute
    ): array {
        $requestedFields = $this->getRequestedSearchFields($targetEntityName, $attribute);
        $validFields = $this->validateSearchFields($requestedFields, $targetMetadata);

        return !empty($validFields) ? $validFields : $this->getAutoDetectedSearchFields($targetMetadata);
    }

    /**
     * Get requested search fields from attribute or defaults.
     *
     * @return array<string>
     */
    private function getRequestedSearchFields(string $targetEntityName, ?ColumnFilter $attribute): array
    {
        if ($attribute !== null && !empty($attribute->searchFields)) {
            return $attribute->searchFields;
        }

        return self::DEFAULT_SEARCH_FIELDS[$targetEntityName] ?? [];
    }

    /**
     * Validate that search fields exist in target metadata.
     *
     * @param array<string> $fields
     * @param ClassMetadata<object> $targetMetadata
     * @return list<string>
     */
    private function validateSearchFields(array $fields, ClassMetadata $targetMetadata): array
    {
        return array_values(array_filter(
            $fields,
            fn(string $field): bool => $targetMetadata->hasField($field)
        ));
    }

    /**
     * Auto-detect searchable fields based on display field priority.
     *
     * @param ClassMetadata<object> $targetMetadata
     * @return list<string>
     */
    private function getAutoDetectedSearchFields(ClassMetadata $targetMetadata): array
    {
        foreach (self::DISPLAY_FIELD_PRIORITY as $field) {
            if ($targetMetadata->hasField($field)) {
                return [$field];
            }
        }

        return ['id'];
    }
}
