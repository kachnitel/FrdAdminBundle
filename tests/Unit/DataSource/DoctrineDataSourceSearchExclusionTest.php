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
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Lifecycle of the global-search exclusion list on DoctrineDataSource:
 * EntityList sets the ADMIN_SHOW-denied columns before query(); they are forwarded to the
 * query service and consumed, so one user's exclusions never outlive the query they were set for
 * (data sources are shared through DataSourceRegistry).
 */
#[UsesClass(Admin::class)]
#[UsesClass(DoctrineDataSource::class)]
#[Group('column-permission-query')]
#[AllowMockObjectsWithoutExpectations]
final class DoctrineDataSourceSearchExclusionTest extends TestCase
{
    /** @var EntityListQueryService&MockObject */
    private EntityListQueryService $queryService;

    private DoctrineDataSource $dataSource;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->queryService = $this->createMock(EntityListQueryService::class);
        $metadata = $this->createMock(ClassMetadata::class);

        $em->method('getClassMetadata')->willReturn($metadata);
        $metadata->method('getSingleIdentifierFieldName')->willReturn('id');

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
            em: $em,
            queryService: $this->queryService,
            filterMetadataProvider: $filterProvider,
            customColumnProvider: $customCols,
            columnAttributeProvider: $colAttrProvider,
            columnTypeMapper: $colTypeMapper,
            filterConverter: new DoctrineFilterConverter(),
            itemValueResolver: new DoctrineItemValueResolver(),
        );
    }

    #[Test]
    public function excludedFieldsAreForwardedToTheQueryService(): void
    {
        $captured = $this->captureExclusions(1);

        $this->dataSource->setSearchExcludedFields(['internalNotes']);
        $this->dataSource->query('term', [], 'id', 'DESC', 1, 20);

        $this->assertSame([['internalNotes']], $captured->getArrayCopy());
    }

    #[Test]
    public function noExclusionsAreForwardedWhenNoneWereSet(): void
    {
        $captured = $this->captureExclusions(1);

        $this->dataSource->query('term', [], 'id', 'DESC', 1, 20);

        $this->assertSame([[]], $captured->getArrayCopy());
    }

    #[Test]
    public function exclusionsAreConsumedByTheFirstQuery(): void
    {
        $captured = $this->captureExclusions(2);

        $this->dataSource->setSearchExcludedFields(['internalNotes']);
        $this->dataSource->query('term', [], 'id', 'DESC', 1, 20);
        $this->dataSource->query('term', [], 'id', 'DESC', 1, 20);

        $this->assertSame([['internalNotes'], []], $captured->getArrayCopy());
    }

    /**
     * @return \ArrayObject<int, list<string>> exclusion list received by each getEntities() call
     */
    private function captureExclusions(int $expectedCalls): \ArrayObject
    {
        /** @var \ArrayObject<int, list<string>> $captured */
        $captured = new \ArrayObject();

        $this->queryService
            ->expects($this->exactly($expectedCalls))
            ->method('getEntities')
            ->willReturnCallback(
                function (
                    string $ec, ?string $rm, string $s, array $cf, array $fm,
                    string $sb, string $sd, int $p, int $ipp,
                    ?string $archive, ?string $scope, array $scopeParams,
                    array $searchExcludedFields,
                ) use ($captured): array {
                    /** @var list<string> $searchExcludedFields */
                    $captured[] = $searchExcludedFields;

                    return ['entities' => [], 'total' => 0, 'page' => 1];
                }
            );

        return $captured;
    }
}
