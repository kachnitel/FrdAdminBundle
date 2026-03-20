<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Kachnitel\AdminBundle\Archive\ArchiveConfig;
use Kachnitel\AdminBundle\Archive\ArchiveService;

/**
 * Facade over ArchiveService and archive-related permission checks for EntityList.
 *
 * Declared public: true in services.yaml so InlineServiceDefinitions cannot
 * inline EntityList's dependency tree when the component is referenced from
 * multiple call sites.
 */
class EntityListArchiveService
{
    public function __construct(
        private readonly ArchiveService $archiveService,
        private readonly EntityListPermissionService $permissionService,
    ) {}

    /**
     * @param class-string $entityClass
     */
    public function resolveConfig(string $entityClass): ?ArchiveConfig
    {
        return $this->archiveService->resolveConfig($entityClass);
    }

    public function canToggle(?ArchiveConfig $config): bool
    {
        return $this->permissionService->canToggleArchive($config);
    }

    public function isArchivedRow(object $entity, ArchiveConfig $config): bool
    {
        return $this->archiveService->isArchived($entity, $config->expression);
    }

    /**
     * Build a DQL WHERE fragment using the entity alias 'e'.
     * Returns null when showArchived is true (no restriction needed) or type is unsupported.
     */
    public function buildDqlCondition(ArchiveConfig $config, bool $showArchived): ?string
    {
        return $this->archiveService->buildDqlCondition(
            'e',
            $config->field,
            $config->doctrineType,
            $showArchived,
        );
    }
}
