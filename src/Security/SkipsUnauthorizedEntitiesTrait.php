<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Security;

/**
 * Shared skip-on-deny loop for batch actions (EntityListBatchService::batchDelete(),
 * ArchiveButton::execute(), and any future batch action).
 *
 * ## Why this is a trait calling isGranted(), not a method on ObjectAuthorizationChecker
 *
 * Consuming this trait means the shared loop only ever calls `isGranted()` on the
 * injected `ObjectAuthorizationChecker` — the one method every existing consumer
 * mocks.
 */
trait SkipsUnauthorizedEntitiesTrait
{
    /**
     * Partition a set of already-resolved entities down to the ones the current user is
     * granted $attribute on, preserving whatever keys the caller used (typically the
     * entity's selected ID).
     *
     * Resolving IDs to entities (and dropping IDs that don't resolve to anything) is the
     * caller's job — pass only entities you've already loaded.
     *
     * @template TKey of array-key
     * @param array<TKey, object> $entitiesByKey
     * @return array<TKey, object> the subset granted $attribute, same keys
     */
    private function filterAuthorizedEntities(
        ObjectAuthorizationChecker $objectAuthChecker,
        string $attribute,
        array $entitiesByKey,
    ): array {
        $granted = [];

        foreach ($entitiesByKey as $key => $entity) {
            if ($objectAuthChecker->isGranted($attribute, $entity)) {
                $granted[$key] = $entity;
            }
        }

        return $granted;
    }
}
