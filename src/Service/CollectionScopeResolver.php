<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
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
 * collection association, the target is an #[Admin] entity, the owner exists,
 * and object-level ADMIN_SHOW on the owner instance.
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
     * @return array{0: string, 1: array<string, mixed>} [DQL fragment using alias "e", parameters]
     *
     * @throws \InvalidArgumentException  malformed scope
     * @throws NotFoundHttpException      unmanaged entity, non-collection property, missing owner
     * @throws AccessDeniedException      class, column or object-level denial
     */
    public function resolve(array $scope): array
    {
        $ownerClass = $scope['ownerClass'] ?? null;
        $ownerId    = $scope['ownerId'] ?? null;
        $property   = $scope['property'] ?? null;

        if (!is_string($ownerClass) || !is_string($property) || !(is_int($ownerId) || is_string($ownerId))) {
            throw new \InvalidArgumentException('Malformed collection scope.');
        }

        /** @var class-string $ownerClass */
        $ownerMeta = $this->getAuthorizedOwnerMetadata($ownerClass, $property);

        $owner = $this->em->find($ownerClass, $ownerId);
        if ($owner === null) {
            $shortName = (new \ReflectionClass($ownerClass))->getShortName();
            throw new NotFoundHttpException(sprintf('%s #%s not found.', $shortName, (string) $ownerId));
        }
        $this->objectAuthChecker->denyAccessUnlessGranted(AdminEntityVoter::ADMIN_SHOW, $owner);

        return $this->buildRestriction($ownerClass, $ownerMeta, $ownerId, $property);
    }

    /**
     * @param class-string $ownerClass
     */
    /**
     * @param class-string $ownerClass
     * @return ClassMetadata<object>
     */
    private function getAuthorizedOwnerMetadata(string $ownerClass, string $property): ClassMetadata
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

        if (!$this->entityDiscovery->isAdminEntity($ownerMeta->getAssociationTargetClass($property))) {
            throw new NotFoundHttpException('Collection target is not managed by the admin.');
        }

        return $ownerMeta;
    }

    /**
     * @param class-string $ownerClass
    * @param ClassMetadata<object> $ownerMeta
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildRestriction(string $ownerClass, ClassMetadata $ownerMeta, string|int $ownerId, string $property): array
    {
        $ownerIdField  = $ownerMeta->getSingleIdentifierFieldName();
        $targetClass   = $ownerMeta->getAssociationTargetClass($property);
        $targetIdField = $this->em->getClassMetadata($targetClass)->getSingleIdentifierFieldName();

        $idParameter = in_array($ownerMeta->getTypeOfField($ownerIdField), self::INTEGER_TYPES, true) && is_numeric($ownerId)
            ? (int) $ownerId
            : $ownerId;

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
