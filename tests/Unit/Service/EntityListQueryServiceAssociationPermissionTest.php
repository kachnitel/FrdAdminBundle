<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Relation and collection filters search fields of the TARGET entity, so they must respect
 * the target class's own #[ColumnPermission] ADMIN_SHOW: filtering Order.customer by an
 * email would otherwise reveal whether that email exists.
 */
#[CoversClass(EntityListQueryService::class)]
#[Group('column-permission-query')]
#[Group('entity-list')]
#[AllowMockObjectsWithoutExpectations]
final class EntityListQueryServiceAssociationPermissionTest extends TestCase
{
    private const ORDER = 'App\\Entity\\Order';
    private const CUSTOMER = 'App\\Entity\\Customer';
    private const COMPANY = 'App\\Entity\\Company';

    /** @var list<string> "Class::property" entries denied for ADMIN_SHOW */
    private array $denied = [];

    private QueryBuilder $qb;

    private EntityListQueryService $service;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);

        $this->qb = new QueryBuilder($em);
        // @phpstan-ignore argument.type (Test entity class doesn't exist)
        $this->qb->select('e')->from(self::ORDER, 'e');

        $em->method('getRepository')->willReturn($repository);
        $em->method('getClassMetadata')->willReturn($metadata);
        $repository->method('createQueryBuilder')->willReturn($this->qb);

        $metadata->method('hasAssociation')->willReturnCallback(static fn (string $s): bool => $s === 'company');
        $metadata->method('getAssociationTargetClass')->willReturn(self::COMPANY);

        $permissions = $this->createMock(ColumnPermissionService::class);
        $permissions->method('canPerformAction')->willReturnCallback(
            function (string $class, string $column, string $action): bool {
                self::assertSame(AdminEntityVoter::ADMIN_SHOW, $action);

                return !in_array($class . '::' . $column, $this->denied, true);
            },
        );

        $this->service = new EntityListQueryService($em, $permissions);
    }

    /**
     * @param list<string> $searchFields
     */
    private function relationFilter(array $searchFields, string $value = 'a@b.com'): string
    {
        $this->service->buildQuery(
            self::ORDER,
            null,
            '',
            ['customer' => $value],
            ['customer' => [
                'type' => 'relation',
                'targetClass' => self::CUSTOMER,
                'searchFields' => $searchFields,
            ]],
            'id',
            'ASC',
        );

        return $this->qb->getDQL();
    }

    /**
     * @param list<string> $searchFields
     */
    private function collectionFilter(array $searchFields): string
    {
        $this->service->buildQuery(
            self::ORDER,
            null,
            '',
            ['customers' => 'a@b.com'],
            ['customers' => [
                'type' => 'collection',
                'targetClass' => self::CUSTOMER,
                'searchFields' => $searchFields,
            ]],
            'id',
            'ASC',
        );

        return $this->qb->getDQL();
    }

    #[Test]
    public function relationFilterSearchesPermittedTargetFields(): void
    {
        $dql = $this->relationFilter(['name', 'email']);

        $this->assertStringContainsString('rel_customer.name', $dql);
        $this->assertStringContainsString('rel_customer.email', $dql, 'control: nothing denied');
    }

    #[Test]
    public function relationFilterDropsDeniedTargetFieldButKeepsPermittedOnes(): void
    {
        $this->denied = [self::CUSTOMER . '::email'];

        $dql = $this->relationFilter(['name', 'email']);

        $this->assertStringContainsString('rel_customer.name', $dql);
        $this->assertStringNotContainsString('email', $dql);
    }

    #[Test]
    public function relationFilterIsDroppedWhenEveryFieldIsDenied(): void
    {
        $this->denied = [self::CUSTOMER . '::email'];

        $dql = $this->relationFilter(['email']);

        $this->assertStringNotContainsString('WHERE', $dql);
        $this->assertStringNotContainsString('rel_customer', $dql);
    }

    #[Test]
    public function numericValueDoesNotFallBackToAnIdMatchWhenEveryFieldIsDenied(): void
    {
        $this->denied = [self::CUSTOMER . '::email'];

        $dql = $this->relationFilter(['email'], '42');

        $this->assertStringNotContainsString('WHERE', $dql);
    }

    #[Test]
    public function dottedPathThroughADeniedAssociationIsDropped(): void
    {
        $this->denied = [self::CUSTOMER . '::company'];

        $dql = $this->relationFilter(['company.name']);

        $this->assertStringNotContainsString('company', $dql);
    }

    #[Test]
    public function dottedPathWithDeniedLeafOnTheNestedClassIsDropped(): void
    {
        $this->denied = [self::COMPANY . '::name'];

        $dql = $this->relationFilter(['company.name', 'company.code']);

        $this->assertStringContainsString('code', $dql, 'control: permitted nested leaf is kept');
        $this->assertStringNotContainsString('.name', $dql);
    }

    #[Test]
    public function collectionFilterDropsDeniedTargetField(): void
    {
        $this->denied = [self::CUSTOMER . '::email'];

        $dql = $this->collectionFilter(['name', 'email']);

        $this->assertStringContainsString('sub_customers.name', $dql);
        $this->assertStringNotContainsString('email', $dql);
    }

    #[Test]
    public function collectionFilterIsDroppedWhenEveryFieldIsDenied(): void
    {
        $this->denied = [self::CUSTOMER . '::email'];

        $this->assertStringNotContainsString('EXISTS', $this->collectionFilter(['email']));
    }

    #[Test]
    public function collectionFilterKeepsPermittedFields(): void
    {
        $this->assertStringContainsString('EXISTS', $this->collectionFilter(['name', 'email']));
    }
}
