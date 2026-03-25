<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\DataSource\DoctrineColumnAttributeProvider;
use Kachnitel\AdminBundle\DataSource\DoctrineColumnTypeMapper;
use Kachnitel\AdminBundle\DataSource\DoctrineCustomColumnProvider;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\DataSource\DoctrineFilterConverter;
use Kachnitel\AdminBundle\DataSource\DoctrineItemValueResolver;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covers the archive DQL condition lifecycle on DoctrineDataSource:
 *
 *   1. Condition set via setArchiveDqlCondition() is forwarded to the query service.
 *   2. Condition is consumed (cleared) after the first query() call — it must NOT
 *      bleed into a subsequent query() call on the same instance.
 *   3. null condition is forwarded as null (no WHERE added).
 *
 * @covers \Kachnitel\AdminBundle\DataSource\DoctrineDataSource::setArchiveDqlCondition
 * @covers \Kachnitel\AdminBundle\DataSource\DoctrineDataSource::query
 * @group archive
 */
class DoctrineDataSourceArchiveConditionTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var EntityListQueryService&MockObject */
    private EntityListQueryService $queryService;

    /** @var ClassMetadata<object>&MockObject */
    private ClassMetadata $metadata;

    private DoctrineDataSource $dataSource;

    protected function setUp(): void
    {
        $this->em           = $this->createMock(EntityManagerInterface::class);
        $this->queryService = $this->createMock(EntityListQueryService::class);
        $this->metadata     = $this->createMock(ClassMetadata::class);

        $this->em->method('getClassMetadata')->willReturn($this->metadata);
        $this->metadata->method('getSingleIdentifierFieldName')->willReturn('id');

        $filterProvider = $this->createMock(FilterMetadataProvider::class);
        $filterProvider->method('getFilters')->willReturn([]);

        $customCols = $this->createMock(DoctrineCustomColumnProvider::class);
        $customCols->method('getCustomColumns')->willReturn([]);

        $colAttrProvider = $this->createMock(DoctrineColumnAttributeProvider::class);
        $colAttrProvider->method('getColumnAttributes')->willReturn([]);
        $colAttrProvider->method('getGroupAttributes')->willReturn([]);

        $colTypeMapper = $this->createMock(DoctrineColumnTypeMapper::class);
        $colTypeMapper->method('getColumnType')->willReturn('string');

        $this->dataSource = new DoctrineDataSource(
            entityClass: 'App\\Entity\\Product', // @phpstan-ignore argument.type
            adminAttribute: new Admin(),
            em: $this->em,
            queryService: $this->queryService,
            filterMetadataProvider: $filterProvider,
            customColumnProvider: $customCols,
            columnAttributeProvider: $colAttrProvider,
            columnTypeMapper: $colTypeMapper,
            filterConverter: new DoctrineFilterConverter(),
            itemValueResolver: new DoctrineItemValueResolver(),
        );
    }

    // private function stubQueryService(?string $capturedCondition = null): void
    // {
    //     $this->queryService
    //         ->method('getEntities')
    //         ->willReturn(['entities' => [], 'total' => 0, 'page' => 1]);
    // }

    // ── Condition forwarded to query service ──────────────────────────────────

    /** @test */
    public function archiveDqlConditionIsPassedToQueryService(): void
    {
        $this->queryService
            ->expects($this->once())
            ->method('getEntities')
            ->with(
                $this->anything(), // entityClass
                $this->anything(), // repositoryMethod
                $this->anything(), // search
                $this->anything(), // columnFilters
                $this->anything(), // filterMetadata
                $this->anything(), // sortBy
                $this->anything(), // sortDirection
                $this->anything(), // page
                $this->anything(), // itemsPerPage
                'e.deletedAt IS NULL', // archiveDqlCondition ← must be forwarded
            )
            ->willReturn(['entities' => [], 'total' => 0, 'page' => 1]);

        $this->dataSource->setArchiveDqlCondition('e.deletedAt IS NULL');
        $this->dataSource->query('', [], 'id', 'DESC', 1, 20);
    }

    /** @test */
    public function nullConditionIsPassedAsNullToQueryService(): void
    {
        $this->queryService
            ->expects($this->once())
            ->method('getEntities')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                null, // archiveDqlCondition must be null when none set
            )
            ->willReturn(['entities' => [], 'total' => 0, 'page' => 1]);

        // No setArchiveDqlCondition() call
        $this->dataSource->query('', [], 'id', 'DESC', 1, 20);
    }

    // ── Condition is cleared after first query() ──────────────────────────────

    /** @test */
    public function archiveConditionIsNotPassedOnSecondQueryCall(): void
    {
        /** @var list<string|null> $capturedConditions */
        $capturedConditions = [];

        $this->queryService
            ->expects($this->exactly(2))
            ->method('getEntities')
            ->willReturnCallback(
                function (
                    string $entityClass,
                    ?string $repositoryMethod,
                    string $search,
                    array $columnFilters,
                    array $filterMetadata,
                    string $sortBy,
                    string $sortDirection,
                    int $page,
                    int $itemsPerPage,
                    ?string $archiveDqlCondition,
                ) use (&$capturedConditions): array {
                    $capturedConditions[] = $archiveDqlCondition;
                    return ['entities' => [], 'total' => 0, 'page' => 1];
                }
            );

        $this->dataSource->setArchiveDqlCondition('e.archived = false');

        // First call — condition must be forwarded
        $this->dataSource->query('', [], 'id', 'DESC', 1, 20);

        // Second call — condition must have been cleared, null expected
        $this->dataSource->query('', [], 'id', 'DESC', 1, 20);

        $this->assertSame('e.archived = false', $capturedConditions[0], 'First query must receive the condition.');
        $this->assertNull($capturedConditions[1], 'Second query must NOT receive the condition — it must be cleared after first use.');
    }

    /** @test */
    public function archiveConditionCanBeSetAgainAfterFirstQuery(): void
    {
        /** @var list<string|null> $capturedConditions */
        $capturedConditions = [];

        $this->queryService
            ->expects($this->exactly(2))
            ->method('getEntities')
            ->willReturnCallback(
                function (
                    string $entityClass,
                    ?string $repositoryMethod,
                    string $search,
                    array $columnFilters,
                    array $filterMetadata,
                    string $sortBy,
                    string $sortDirection,
                    int $page,
                    int $itemsPerPage,
                    ?string $archiveDqlCondition,
                ) use (&$capturedConditions): array {
                    $capturedConditions[] = $archiveDqlCondition;
                    return ['entities' => [], 'total' => 0, 'page' => 1];
                }
            );

        $this->dataSource->setArchiveDqlCondition('e.archived = false');
        $this->dataSource->query('', [], 'id', 'DESC', 1, 20);

        // Simulate EntityList calling setArchiveDqlCondition() again before next render
        $this->dataSource->setArchiveDqlCondition('e.archived = false');
        $this->dataSource->query('', [], 'id', 'DESC', 1, 20);

        $this->assertSame('e.archived = false', $capturedConditions[0]);
        $this->assertSame('e.archived = false', $capturedConditions[1], 'Re-setting condition before second query must work.');
    }

    /** @test */
    public function settingNullConditionExplicitlyAfterSetClearsIt(): void
    {
        /** @var list<string|null> $capturedConditions */
        $capturedConditions = [];

        $this->queryService
            ->expects($this->once())
            ->method('getEntities')
            ->willReturnCallback(
                function (
                    string $ec, ?string $rm, string $s, array $cf, array $fm,
                    string $sb, string $sd, int $p, int $ipp,
                    ?string $archiveDqlCondition,
                ) use (&$capturedConditions): array {
                    $capturedConditions[] = $archiveDqlCondition;
                    return ['entities' => [], 'total' => 0, 'page' => 1];
                }
            );

        $this->dataSource->setArchiveDqlCondition('e.archived = false');
        $this->dataSource->setArchiveDqlCondition(null); // Overwrite with null
        $this->dataSource->query('', [], 'id', 'DESC', 1, 20);

        $this->assertNull($capturedConditions[0], 'Explicitly setting null should override the previous condition.');
    }
}
