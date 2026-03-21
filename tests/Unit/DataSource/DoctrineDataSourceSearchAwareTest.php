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
use Kachnitel\DataSourceContracts\SearchAwareDataSourceInterface;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests that DoctrineDataSource implements SearchAwareDataSourceInterface
 * and returns correct human-readable column labels for searchable fields.
 *
 * @group global-search
 * @covers \Kachnitel\AdminBundle\DataSource\DoctrineDataSource::getGlobalSearchColumnLabels
 */
class DoctrineDataSourceSearchAwareTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var EntityListQueryService&MockObject */
    private EntityListQueryService $queryService;

    /** @var FilterMetadataProvider&MockObject */
    private FilterMetadataProvider $filterMetadataProvider;

    /** @var DoctrineCustomColumnProvider&MockObject */
    private DoctrineCustomColumnProvider $customColumnProvider;

    /** @var DoctrineColumnAttributeProvider&MockObject */
    private DoctrineColumnAttributeProvider $columnAttrProvider;

    /** @var DoctrineColumnTypeMapper&MockObject */
    private DoctrineColumnTypeMapper $columnTypeMapper;

    /** @var ClassMetadata<object>&MockObject */
    private ClassMetadata $classMetadata;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->queryService = $this->createMock(EntityListQueryService::class);
        $this->filterMetadataProvider = $this->createMock(FilterMetadataProvider::class);
        $this->filterMetadataProvider->method('getFilters')->willReturn([]);

        $this->customColumnProvider = $this->createMock(DoctrineCustomColumnProvider::class);
        $this->customColumnProvider->method('getCustomColumns')->willReturn([]);

        $this->columnAttrProvider = $this->createMock(DoctrineColumnAttributeProvider::class);
        $this->columnAttrProvider->method('getColumnAttributes')->willReturn([]);
        $this->columnAttrProvider->method('getGroupAttributes')->willReturn([]);

        $this->columnTypeMapper = $this->createMock(DoctrineColumnTypeMapper::class);
        $this->columnTypeMapper->method('getColumnType')->willReturn('string');

        /** @var ClassMetadata<object>&MockObject $classMetadata */
        $classMetadata = $this->createMock(ClassMetadata::class);
        $this->classMetadata = $classMetadata;

        $this->em->method('getClassMetadata')->willReturn($this->classMetadata);
    }

    /**
     * @param class-string $entityClass
     */
    private function makeDataSource(?string $entityClass = null, ?Admin $admin = null): DoctrineDataSource
    {
        /** @var class-string|null $entityClass */
        $entityClass ??= 'App\\Entity\\Product'; // @phpstan-ignore varTag.nativeType
        return new DoctrineDataSource(
            entityClass: $entityClass,
            adminAttribute: $admin ?? new Admin(),
            em: $this->em,
            queryService: $this->queryService,
            filterMetadataProvider: $this->filterMetadataProvider,
            customColumnProvider: $this->customColumnProvider,
            columnAttributeProvider: $this->columnAttrProvider,
            columnTypeMapper: $this->columnTypeMapper,
            filterConverter: new DoctrineFilterConverter(),
            itemValueResolver: new DoctrineItemValueResolver(),
        );
    }

    /**
     * @param array<string, string> $fields field name => Doctrine type string
     */
    private function stubDoctrineFields(array $fields): void
    {
        $this->classMetadata->method('getFieldNames')->willReturn(array_keys($fields));
        $this->classMetadata->method('getTypeOfField')
            ->willReturnCallback(fn (string $f) => $fields[$f] ?? 'string');
        $this->classMetadata->method('hasField')->willReturn(true);
        $this->classMetadata->method('hasAssociation')->willReturn(false);
        $this->classMetadata->method('isSingleValuedAssociation')->willReturn(false);
        $this->classMetadata->method('isCollectionValuedAssociation')->willReturn(false);
        $this->classMetadata->method('getAssociationNames')->willReturn([]);
    }

    /** @test */
    public function implementsSearchAwareDataSourceInterface(): void
    {
        $interfaces = class_implements(DoctrineDataSource::class);
        $this->assertContains(
            SearchAwareDataSourceInterface::class,
            $interfaces !== false ? $interfaces : []
        );
    }

    /** @test */
    public function returnsEmptyArrayWhenNoSearchableFields(): void
    {
        $this->queryService->method('getSearchableFieldNames')->willReturn([]);

        $labels = $this->makeDataSource()->getGlobalSearchColumnLabels();

        $this->assertSame([], $labels);
    }

    /** @test */
    public function returnsLabelsForSearchableColumnsVisibleInList(): void
    {
        $this->stubDoctrineFields(['id' => 'integer', 'name' => 'string', 'email' => 'string']);
        $this->queryService->method('getSearchableFieldNames')->willReturn(['name', 'email']);

        $labels = $this->makeDataSource()->getGlobalSearchColumnLabels();

        $this->assertContains('Name', $labels);
        $this->assertContains('Email', $labels);
    }

    /** @test */
    public function excludesSearchableFieldsNotPresentAsColumns(): void
    {
        $this->stubDoctrineFields(['id' => 'integer', 'name' => 'string']);
        $this->queryService->method('getSearchableFieldNames')->willReturn(['name', 'hiddenField']);

        $admin = new Admin(columns: ['id', 'name']);
        $labels = $this->makeDataSource(admin: $admin)->getGlobalSearchColumnLabels();

        $this->assertContains('Name', $labels);
        $this->assertNotContains('Hidden field', $labels);
    }

    /** @test */
    public function returnsLabelsForStringAndTextFieldsInColumns(): void
    {
        $this->stubDoctrineFields([
            'id'          => 'integer',
            'name'        => 'string',
            'description' => 'text',
            'price'       => 'decimal',
        ]);

        $this->queryService->method('getSearchableFieldNames')
            ->willReturn(['name', 'description']);

        $labels = $this->makeDataSource()->getGlobalSearchColumnLabels();

        $this->assertContains('Name', $labels);
        $this->assertContains('Description', $labels);
        $this->assertNotContains('Id', $labels);
        $this->assertNotContains('Price', $labels);
        $this->assertCount(2, $labels);
    }

    /** @test */
    public function skipsSearchableFieldsNotInVisibleColumns(): void
    {
        $this->stubDoctrineFields(['id' => 'integer', 'name' => 'string', 'internalCode' => 'string']);
        $this->queryService->method('getSearchableFieldNames')
            ->willReturn(['name', 'internalCode']);

        $labels = $this->makeDataSource(admin: new Admin(columns: ['id', 'name']))->getGlobalSearchColumnLabels();

        $this->assertContains('Name', $labels);
        $this->assertNotContains('Internal Code', $labels);
        $this->assertCount(1, $labels);
    }
}
