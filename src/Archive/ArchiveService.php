<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Archive;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;

/**
 * Resolves archive/soft-delete configuration and provides helpers for
 * archive-aware filtering.
 *
 * Supports simple field expressions of the form `item.fieldName` or
 * `entity.fieldName`. Complex expressions are supported for per-entity
 * row evaluation but cannot be translated to a DQL WHERE clause —
 * in that case resolveConfig() returns null.
 */
class ArchiveService
{
    private const BOOLEAN_TYPES = ['boolean'];

    private const DATETIME_TYPES = [
        'datetime', 'datetime_immutable',
        'datetimetz', 'datetimetz_immutable',
        'date', 'date_immutable',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EntityDiscoveryService $entityDiscovery,
        private readonly RowActionExpressionLanguage $expressionLanguage,
        private readonly ?string $globalExpression,
        private readonly ?string $globalRole,
    ) {}

    /**
     * Resolve the archive configuration for an entity class.
     *
     * Returns null when no expression is configured, entity disabled it,
     * or the field type is not supported for DQL generation.
     *
     * @param class-string $entityClass
     */
    public function resolveConfig(string $entityClass): ?ArchiveConfig
    {
        $adminAttr = $this->entityDiscovery->getAdminAttribute($entityClass);

        if ($adminAttr !== null && $adminAttr->isArchiveDisabled()) {
            return null;
        }

        $expression = $this->resolveExpression($adminAttr);
        if ($expression === null) {
            return null;
        }

        $fieldAndType = $this->resolveFieldAndType($entityClass, $expression);
        if ($fieldAndType === null) {
            return null;
        }

        return new ArchiveConfig(
            expression: $expression,
            field: $fieldAndType['field'],
            doctrineType: $fieldAndType['type'],
            role: $this->resolveRole($adminAttr),
        );
    }

    private function resolveExpression(?\Kachnitel\AdminBundle\Attribute\Admin $adminAttr): ?string
    {
        $fromAttr = $adminAttr?->getArchiveExpression();
        return $fromAttr ?? $this->globalExpression;
    }

    private function resolveRole(?\Kachnitel\AdminBundle\Attribute\Admin $adminAttr): ?string
    {
        $fromAttr = $adminAttr?->getArchiveRole();
        return $fromAttr ?? $this->globalRole;
    }

    /**
     * @return array{field: string, type: string}|null
     */
    private function resolveFieldAndType(string $entityClass, string $expression): ?array
    {
        $field = $this->extractField($expression);
        if ($field === null) {
            return null;
        }

        $metadata = $this->em->getClassMetadata($entityClass);
        if (!$metadata->hasField($field)) {
            return null;
        }

        $doctrineType = $metadata->getTypeOfField($field) ?? '';
        if (!$this->isSupportedType($doctrineType)) {
            return null;
        }

        return ['field' => $field, 'type' => $doctrineType];
    }

    /**
     * Extract the Doctrine field name from a simple `item.fieldName` expression.
     * Returns null for complex expressions that cannot be trivially mapped to DQL.
     */
    public function extractField(string $expression): ?string
    {
        $expression = trim($expression);

        if (!preg_match('/^(?:item|entity)\.([a-zA-Z_][a-zA-Z0-9_]*)$/', $expression, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Generate a DQL WHERE clause fragment for the archive filter.
     * Returns null when showArchived is true (no restriction needed) or type is unsupported.
     */
    public function buildDqlCondition(
        string $alias,
        string $field,
        string $doctrineType,
        bool $showArchived,
    ): ?string {
        if ($showArchived) {
            return null;
        }

        if (in_array($doctrineType, self::BOOLEAN_TYPES, true)) {
            return sprintf('%s.%s = false', $alias, $field);
        }

        if (in_array($doctrineType, self::DATETIME_TYPES, true)) {
            return sprintf('%s.%s IS NULL', $alias, $field);
        }

        return null;
    }

    /**
     * Evaluate the archive expression against a loaded entity.
     * Returns false on any evaluation error.
     */
    public function isArchived(object $entity, string $expression): bool
    {
        return $this->expressionLanguage->evaluate($expression, $entity);
    }

    private function isSupportedType(string $doctrineType): bool
    {
        return in_array($doctrineType, self::BOOLEAN_TYPES, true)
            || in_array($doctrineType, self::DATETIME_TYPES, true);
    }
}
