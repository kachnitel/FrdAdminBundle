<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Kachnitel\AdminBundle\Service\CollectionScopeResolver;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * \stdClass stands in for the owner entity, \ArrayObject for the collection target.
 */
#[CoversClass(CollectionScopeResolver::class)]
#[Group('collection-dialog')]
#[Group('collection-scope')]
final class CollectionScopeResolverTest extends TestCase
{
    private const SCOPE = ['ownerClass' => \stdClass::class, 'ownerId' => '7', 'property' => 'items'];

    #[Test]
    public function buildsExactSubqueryWithBoundOwnerId(): void
    {
        [$dql, $params] = $this->makeResolver()->resolve(self::SCOPE);

        $this->assertSame(
            'e.id IN (SELECT scope_t.id FROM stdClass scope_o JOIN scope_o.items scope_t WHERE scope_o.id = :scope_owner_id)',
            $dql,
        );
        $this->assertSame(['scope_owner_id' => '7'], $params);
    }

    #[Test]
    public function castsNumericOwnerIdForIntegerKeys(): void
    {
        [, $params] = $this->makeResolver(idType: 'integer')->resolve(self::SCOPE);

        $this->assertSame(['scope_owner_id' => 7], $params);
    }

    #[Test]
    public function keepsStringOwnerIdForNonIntegerKeys(): void
    {
        [, $params] = $this->makeResolver(idType: 'guid')->resolve(
            ['ownerClass' => \stdClass::class, 'ownerId' => 'abc-123', 'property' => 'items'],
        );

        $this->assertSame(['scope_owner_id' => 'abc-123'], $params);
    }

    #[Test]
    public function checksShowOnTheOwnerInstance(): void
    {
        $owner = new \stdClass();
        $objectAuth = $this->createMock(ObjectAuthorizationChecker::class);
        $objectAuth->expects($this->once())
            ->method('denyAccessUnlessGranted')
            ->with(AdminEntityVoter::ADMIN_SHOW, $this->identicalTo($owner));

        $this->makeResolver(owner: $owner, objectAuth: $objectAuth)->resolve(self::SCOPE);
    }

    #[Test]
    public function rejectsMalformedScope(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeResolver()->resolve(['ownerClass' => 1, 'ownerId' => '7', 'property' => 'items']);
    }

    #[Test]
    public function rejectsEmptyScope(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeResolver()->resolve([]);
    }

    #[Test]
    public function rejectsOwnerNotManagedByAdmin(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->makeResolver(ownerAdmin: false)->resolve(self::SCOPE);
    }

    #[Test]
    public function rejectsTargetNotManagedByAdmin(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->makeResolver(targetAdmin: false)->resolve(self::SCOPE);
    }

    #[Test]
    public function rejectsClassLevelShowDenial(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->makeResolver(show: false)->resolve(self::SCOPE);
    }

    #[Test]
    public function rejectsColumnPermissionDenial(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->makeResolver(column: false)->resolve(self::SCOPE);
    }

    #[Test]
    public function rejectsObjectLevelDenial(): void
    {
        $objectAuth = $this->createStub(ObjectAuthorizationChecker::class);
        $objectAuth->method('denyAccessUnlessGranted')->willThrowException(new AccessDeniedException());

        $this->expectException(AccessDeniedException::class);

        $this->makeResolver(objectAuth: $objectAuth)->resolve(self::SCOPE);
    }

    #[Test]
    public function rejectsNonCollectionProperty(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->makeResolver(isCollection: false)->resolve(self::SCOPE);
    }

    #[Test]
    public function rejectsMissingOwner(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->makeResolver(owner: null)->resolve(self::SCOPE);
    }

    private function makeResolver(
        bool $ownerAdmin = true,
        bool $targetAdmin = true,
        bool $show = true,
        bool $column = true,
        bool $isCollection = true,
        ?object $owner = new \stdClass(),
        ?ObjectAuthorizationChecker $objectAuth = null,
        ?string $idType = null,
    ): CollectionScopeResolver {
        $discovery = $this->createStub(EntityDiscoveryService::class);
        $discovery->method('isAdminEntity')->willReturnCallback(
            static fn (string $class): bool => $class === \stdClass::class ? $ownerAdmin : $targetAdmin
        );

        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn($show);

        $columns = $this->createStub(ColumnPermissionService::class);
        $columns->method('canPerformAction')->willReturn($column);

        /** @var ClassMetadata<object>&\PHPUnit\Framework\MockObject\Stub $metadata */
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('isCollectionValuedAssociation')->willReturn($isCollection);
        $metadata->method('getAssociationTargetClass')->willReturn(\ArrayObject::class);
        $metadata->method('getSingleIdentifierFieldName')->willReturn('id');
        $metadata->method('getTypeOfField')->willReturn($idType);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($metadata);
        $em->method('find')->willReturn($owner);

        return new CollectionScopeResolver(
            $em,
            $discovery,
            $auth,
            $columns,
            $objectAuth ?? $this->createStub(ObjectAuthorizationChecker::class),
        );
    }
}
