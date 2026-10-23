<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Authorizes and translates an embedded EntityList's `scope` LiveProp
 * ({ownerClass, ownerId, property}) into a DQL restriction on alias `e`.
 *
 * Checks, in order: owner is an #[Admin] entity, class-level ADMIN_SHOW on the
 * owner, #[ColumnPermission] ADMIN_SHOW on the property, the property is a
 * collection association, the target is an #[Admin] entity and is the entity the
 * list shows, the owner id is valid for the owner's identifier type, the owner
 * exists, and object-level ADMIN_SHOW on the owner instance.
 *
 * The restriction is an exact `IN (subquery)` on the target's identifier, not a
 * column filter: it is correct for OneToMany, bidirectional and unidirectional
 * ManyToMany alike, and cannot be confused with the LIKE-based relation filter.
 *
 * @see \Kachnitel\AdminBundle\Tests\Service\CollectionScopeResolverTest
 */
class CollectionScopeResolver
{
    private const INTEGER_TYPES = ['integer', 'smallint', 'bigint'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EntityDiscoveryService $entityDiscovery,
        private readonly AuthorizationCheckerInterface $authChecker,
        private readonly ColumnPermissionService $columnPermissions,
        private readonly ObjectAuthorizationChecker $objectAuthChecker,
    ) {}

    /**
     * @param array<string, mixed> $scope
     * @param class-string $listEntityClass Entity class of the list being scoped; the
     *                                      collection's target must be this entity (or a subclass)
     * @return array{0: string, 1: array<string, mixed>} [DQL fragment using alias "e", parameters]
     *
     * @throws \InvalidArgumentException  malformed scope
     * @throws NotFoundHttpException      unmanaged entity, non-collection property, target/list mismatch,
     *                                    invalid or missing owner
     * @throws AccessDeniedException      class, column or object-level denial
     */
    public function resolve(array $scope, string $listEntityClass): array
    {
        $ownerClass = $scope['ownerClass'] ?? null;
        $ownerId    = $scope['ownerId'] ?? null;
        $property   = $scope['property'] ?? null;

        if (!is_string($ownerClass) || !is_string($property) || !(is_int($ownerId) || is_string($ownerId))) {
            throw new \InvalidArgumentException('Malformed collection scope.');
        }

        /** @var class-string $ownerClass */
        $ownerMeta = $this->getAuthorizedOwnerMetadata($ownerClass, $property, $listEntityClass);

        // Normalize before querying: a string bound to an integer key is a driver error
        // on some platforms (500) rather than a clean "not found".
        $ownerIdField = $this->getSingleIdentifierField($ownerMeta);
        $idParameter  = $this->normalizeOwnerId($ownerMeta, $ownerIdField, $ownerId);

        $owner = $this->em->find($ownerClass, $idParameter);
        if ($owner === null) {
            $shortName = (new \ReflectionClass($ownerClass))->getShortName();
            throw new NotFoundHttpException(sprintf('%s #%s not found.', $shortName, (string) $ownerId));
        }
        $this->objectAuthChecker->denyAccessUnlessGranted(AdminEntityVoter::ADMIN_SHOW, $owner);

        return $this->buildRestriction($ownerClass, $ownerMeta, $ownerIdField, $idParameter, $property);
    }

    /**
     * @param class-string $ownerClass
     * @param class-string $listEntityClass
     * @return ClassMetadata<object>
     */
    private function getAuthorizedOwnerMetadata(string $ownerClass, string $property, string $listEntityClass): ClassMetadata
    {
        if (!$this->entityDiscovery->isAdminEntity($ownerClass)) {
            throw new NotFoundHttpException('Scope owner is not managed by the admin.');
        }

        $shortName = (new \ReflectionClass($ownerClass))->getShortName();
        if (!$this->authChecker->isGranted(AdminEntityVoter::ADMIN_SHOW, $shortName)
            || !$this->columnPermissions->canPerformAction($ownerClass, $property, AdminEntityVoter::ADMIN_SHOW)
        ) {
            throw new AccessDeniedException(sprintf('Access denied: ADMIN_SHOW on %s::$%s.', $shortName, $property));
        }

        $ownerMeta = $this->em->getClassMetadata($ownerClass);
        if (!$ownerMeta->isCollectionValuedAssociation($property)) {
            throw new NotFoundHttpException(sprintf('"%s" is not a collection association.', $property));
        }

        $targetClass = $ownerMeta->getAssociationTargetClass($property);
        if (!$this->entityDiscovery->isAdminEntity($targetClass)) {
            throw new NotFoundHttpException('Collection target is not managed by the admin.');
        }

        // Without this, /admin/product?collection=Order:12:lines would apply Order line ids
        // to Product rows (wrong rows, or a DQL error when the id fields differ).
        if (!is_a($targetClass, $listEntityClass, true)) {
            throw new NotFoundHttpException('Collection target does not match the listed entity.');
        }

        return $ownerMeta;
    }

    /**
     * @param ClassMetadata<object> $ownerMeta
     */
    private function getSingleIdentifierField(ClassMetadata $ownerMeta): string
    {
        try {
            return $ownerMeta->getSingleIdentifierFieldName();
        } catch (MappingException) {
            throw new NotFoundHttpException('Collection scopes require a single-column owner identifier.');
        }
    }

    /**
     * @param ClassMetadata<object> $ownerMeta
     */
    private function normalizeOwnerId(ClassMetadata $ownerMeta, string $ownerIdField, string|int $ownerId): string|int
    {
        if (!in_array($ownerMeta->getTypeOfField($ownerIdField), self::INTEGER_TYPES, true)) {
            return $ownerId;
        }

        if (is_int($ownerId)) {
            return $ownerId;
        }

        if (preg_match('/^-?\d+$/D', $ownerId) !== 1) {
            throw new NotFoundHttpException('Invalid owner identifier.');
        }

        return (int) $ownerId;
    }

    /**
     * @param class-string $ownerClass
     * @param ClassMetadata<object> $ownerMeta
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildRestriction(
        string $ownerClass,
        ClassMetadata $ownerMeta,
        string $ownerIdField,
        string|int $idParameter,
        string $property,
    ): array {
        $targetClass   = $ownerMeta->getAssociationTargetClass($property);
        $targetIdField = $this->em->getClassMetadata($targetClass)->getSingleIdentifierFieldName();

        // Class and property names were validated above (admin-entity map / Doctrine
        // metadata), so interpolating them is safe; the owner id is bound.
        $dql = sprintf(
            'e.%1$s IN (SELECT scope_t.%1$s FROM %2$s scope_o JOIN scope_o.%3$s scope_t WHERE scope_o.%4$s = :scope_owner_id)',
            $targetIdField,
            $ownerClass,
            $property,
            $ownerIdField,
        );

        return [$dql, ['scope_owner_id' => $idParameter]];
    }
}
