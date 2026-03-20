<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Runtime;

use Kachnitel\AdminBundle\Archive\ArchiveService;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Twig runtime for archive-related functions.
 *
 * Provides `admin_is_archived(entity)` used by show/edit page templates
 * to display the archive warning banner.
 */
class AdminArchiveRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly ?ArchiveService $archiveService,
    ) {}

    /**
     * Return true when the entity is archived according to the configured expression.
     * Returns false when archive is not configured or on any evaluation error.
     */
    public function isArchived(object $entity): bool
    {
        if ($this->archiveService === null) {
            return false;
        }

        /** @var class-string $entityClass */
        $entityClass = $this->resolveClass($entity);
        $config = $this->archiveService->resolveConfig($entityClass);

        if ($config === null) {
            return false;
        }

        return $this->archiveService->isArchived($entity, $config->expression);
    }

    private function resolveClass(object $entity): string
    {
        $class = $entity::class;
        if (str_contains($class, 'Proxies\\__CG__\\')) {
            $parent = get_parent_class($entity);
            return $parent !== false ? $parent : $class;
        }

        return $class;
    }
}
