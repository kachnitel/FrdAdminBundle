<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Archive;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Performs the actual archive and unarchive field mutations on an entity.
 *
 * Supports all Doctrine field types that ArchiveService can resolve:
 *   - boolean      → true (archive) / false (unarchive)
 *   - datetime     → new \DateTime() / null
 *   - datetime_immutable → new \DateTimeImmutable() / null
 *   - datetimetz   → new \DateTime() / null
 *   - datetimetz_immutable → new \DateTimeImmutable() / null
 *   - date         → new \DateTime('today') / null
 *   - date_immutable → new \DateTimeImmutable('today') / null
 *
 * Uses Symfony's PropertyAccessor so the entity only needs a conventional
 * setter (e.g. setDeletedAt(), setArchived()), no direct property access required.
 */
class ArchiveEntityService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PropertyAccessorInterface $propertyAccessor,
    ) {}

    /**
     * Set the archive field to mark the entity as archived, then flush.
     */
    public function archive(object $entity, ArchiveConfig $config): void
    {
        $value = $this->resolveArchiveValue($config->doctrineType);
        $this->propertyAccessor->setValue($entity, $config->field, $value);
        $this->em->flush();
    }

    /**
     * Clear the archive field to restore the entity, then flush.
     */
    public function unarchive(object $entity, ArchiveConfig $config): void
    {
        $value = $this->resolveUnarchiveValue($config->doctrineType);
        $this->propertyAccessor->setValue($entity, $config->field, $value);
        $this->em->flush();
    }

    private function resolveArchiveValue(string $doctrineType): mixed
    {
        return match ($doctrineType) {
            'boolean'                                                            => true,
            'datetime', 'datetimetz'                                            => new \DateTime(),
            'datetime_immutable', 'datetimetz_immutable'                        => new \DateTimeImmutable(),
            'date'                                                               => new \DateTime('today'),
            'date_immutable'                                                     => new \DateTimeImmutable('today'),
            default => throw new \InvalidArgumentException(
                sprintf('Unsupported archive field type: %s', $doctrineType)
            ),
        };
    }

    private function resolveUnarchiveValue(string $doctrineType): mixed
    {
        if ($doctrineType === 'boolean') {
            return false;
        }

        // All datetime / date variants: null means "not archived"
        if (in_array($doctrineType, [
            'datetime', 'datetime_immutable',
            'datetimetz', 'datetimetz_immutable',
            'date', 'date_immutable',
        ], true)) {
            return null;
        }

        throw new \InvalidArgumentException(
            sprintf('Unsupported archive field type: %s', $doctrineType)
        );
    }
}
