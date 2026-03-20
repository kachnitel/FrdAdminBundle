<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Archive;

/**
 * Resolved archive/soft-delete configuration for a specific entity.
 *
 * Created by ArchiveService by merging global YAML config with entity-level
 * #[Admin(archiveExpression: ...)] overrides.
 *
 * Null means archive filtering is not configured for this entity.
 */
final readonly class ArchiveConfig
{
    /**
     * @param string      $expression   ExpressionLanguage expression, e.g. 'item.archived'
     * @param string      $field        Extracted Doctrine field name, e.g. 'archived'
     * @param string      $doctrineType Doctrine column type ('boolean', 'datetime', etc.)
     * @param string|null $role         Role required to toggle the filter; null = anyone
     */
    public function __construct(
        public readonly string $expression,
        public readonly string $field,
        public readonly string $doctrineType,
        public readonly ?string $role,
    ) {}
}
