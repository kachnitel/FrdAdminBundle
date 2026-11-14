<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\AdminBundle\Service\ListQueryColumnGuard;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The guard in isolation; EntityListQueryService*PermissionTest cover it through the service.
 */
#[PHPUnit\CoversClass(ListQueryColumnGuard::class)]
#[PHPUnit\Group('column-permission-query')]
#[PHPUnit\AllowMockObjectsWithoutExpectations]
final class ListQueryColumnGuardTest extends TestCase
{
    private const CUSTOMER = 'App\\Entity\\Customer';

    /** @var ColumnPermissionService&MockObject */
    private ColumnPermissionService $permissions;

    private ListQueryColumnGuard $guard;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $em->method('getClassMetadata')->willReturn($metadata);
        $metadata->method('getIdentifierFieldNames')->willReturn(['uuid']);
        $metadata->method('hasAssociation')->willReturn(false);

        $this->permissions = $this->createMock(ColumnPermissionService::class);
        $this->guard = new ListQueryColumnGuard($em, $this->permissions);
    }

    #[PHPUnit\Test]
    public function deniedColumnsAreTheShowDeniedOnes(): void
    {
        $this->permissions->expects($this->once())->method('getDeniedColumnsForAction')
            ->with(self::CUSTOMER, AdminEntityVoter::ADMIN_SHOW)
            ->willReturn([2 => 'salary']);

        $this->assertSame(['salary'], $this->guard->deniedColumns(self::CUSTOMER));
    }

    #[PHPUnit\Test]
    public function sortFieldFallsBackToTheIdentifierForADeniedColumn(): void
    {
        $this->assertSame('uuid', $this->guard->sortField(self::CUSTOMER, 'salary', ['salary']));
        $this->assertSame('uuid', $this->guard->sortField(self::CUSTOMER, 'salary.amount', ['salary']));
        $this->assertSame('name', $this->guard->sortField(self::CUSTOMER, 'name', ['salary']), 'control');
    }

    #[PHPUnit\Test]
    public function filterMetadataWithoutSearchFieldsPassesThrough(): void
    {
        $metadata = ['type' => 'relation', 'targetClass' => self::CUSTOMER];

        $this->assertSame($metadata, $this->guard->restrictAssociationSearchFields($metadata));
    }

    #[PHPUnit\Test]
    public function deniedSearchFieldsAreDroppedAndPermittedOnesKept(): void
    {
        $this->permissions->method('canPerformAction')->willReturnCallback(
            static fn (string $class, string $column): bool => $column !== 'email',
        );

        $result = $this->guard->restrictAssociationSearchFields([
            'type' => 'relation',
            'targetClass' => self::CUSTOMER,
            'searchFields' => ['name', 'email'],
        ]);

        $this->assertSame(['name'], $result['searchFields'] ?? null);
        $this->assertSame('relation', $result['type'] ?? null);
    }

    #[PHPUnit\Test]
    public function filterIsDroppedWhenEverySearchFieldIsDenied(): void
    {
        $this->permissions->method('canPerformAction')->willReturn(false);

        $this->assertNull($this->guard->restrictAssociationSearchFields([
            'type' => 'relation',
            'targetClass' => self::CUSTOMER,
            'searchFields' => ['email'],
        ]));
    }
}
