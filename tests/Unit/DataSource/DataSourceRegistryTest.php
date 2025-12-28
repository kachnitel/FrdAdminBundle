<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\DataSourceProviderInterface;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSourceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DataSourceRegistryTest extends TestCase
{
    /** @var DoctrineDataSourceFactory&MockObject */
    private DoctrineDataSourceFactory $doctrineFactory;

    protected function setUp(): void
    {
        $this->doctrineFactory = $this->createMock(DoctrineDataSourceFactory::class);
    }

    public function testAllReturnsEmptyArrayWhenNoDataSources(): void
    {
        $this->doctrineFactory->method('createAll')->willReturn([]);

        $registry = new DataSourceRegistry([], [], $this->doctrineFactory);

        $this->assertSame([], $registry->all());
    }

    public function testAllReturnsDoctrineDataSources(): void
    {
        $doctrineDs = $this->createMock(DoctrineDataSource::class);
        $doctrineDs->method('getIdentifier')->willReturn('Product');

        $this->doctrineFactory->method('createAll')->willReturn([$doctrineDs]);

        $registry = new DataSourceRegistry([], [], $this->doctrineFactory);

        $result = $registry->all();

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('Product', $result);
        $this->assertSame($doctrineDs, $result['Product']);
    }

    public function testAllReturnsDataSourcesFromProviders(): void
    {
        $this->doctrineFactory->method('createAll')->willReturn([]);

        $providerDs = $this->createMock(DataSourceInterface::class);
        $providerDs->method('getIdentifier')->willReturn('AuditLog');

        $provider = $this->createMock(DataSourceProviderInterface::class);
        $provider->method('getDataSources')->willReturn([$providerDs]);

        $registry = new DataSourceRegistry([], [$provider], $this->doctrineFactory);

        $result = $registry->all();

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('AuditLog', $result);
        $this->assertSame($providerDs, $result['AuditLog']);
    }

    public function testAllReturnsCustomDataSources(): void
    {
        $this->doctrineFactory->method('createAll')->willReturn([]);

        $customDs = $this->createMock(DataSourceInterface::class);
        $customDs->method('getIdentifier')->willReturn('CustomSource');

        $registry = new DataSourceRegistry([$customDs], [], $this->doctrineFactory);

        $result = $registry->all();

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('CustomSource', $result);
        $this->assertSame($customDs, $result['CustomSource']);
    }

    public function testAllMergesAllSourceTypes(): void
    {
        $doctrineDs = $this->createMock(DoctrineDataSource::class);
        $doctrineDs->method('getIdentifier')->willReturn('Product');

        $this->doctrineFactory->method('createAll')->willReturn([$doctrineDs]);

        $providerDs = $this->createMock(DataSourceInterface::class);
        $providerDs->method('getIdentifier')->willReturn('AuditLog');

        $provider = $this->createMock(DataSourceProviderInterface::class);
        $provider->method('getDataSources')->willReturn([$providerDs]);

        $customDs = $this->createMock(DataSourceInterface::class);
        $customDs->method('getIdentifier')->willReturn('CustomSource');

        $registry = new DataSourceRegistry([$customDs], [$provider], $this->doctrineFactory);

        $result = $registry->all();

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('Product', $result);
        $this->assertArrayHasKey('AuditLog', $result);
        $this->assertArrayHasKey('CustomSource', $result);
    }

    public function testCustomDataSourcesOverrideDoctrineOnes(): void
    {
        $doctrineDs = $this->createMock(DoctrineDataSource::class);
        $doctrineDs->method('getIdentifier')->willReturn('Product');

        $this->doctrineFactory->method('createAll')->willReturn([$doctrineDs]);

        $customDs = $this->createMock(DataSourceInterface::class);
        $customDs->method('getIdentifier')->willReturn('Product'); // Same identifier

        $registry = new DataSourceRegistry([$customDs], [], $this->doctrineFactory);

        $result = $registry->all();

        $this->assertCount(1, $result);
        $this->assertSame($customDs, $result['Product']); // Custom wins
    }

    public function testCustomDataSourcesOverrideProviderOnes(): void
    {
        $this->doctrineFactory->method('createAll')->willReturn([]);

        $providerDs = $this->createMock(DataSourceInterface::class);
        $providerDs->method('getIdentifier')->willReturn('AuditLog');

        $provider = $this->createMock(DataSourceProviderInterface::class);
        $provider->method('getDataSources')->willReturn([$providerDs]);

        $customDs = $this->createMock(DataSourceInterface::class);
        $customDs->method('getIdentifier')->willReturn('AuditLog'); // Same identifier

        $registry = new DataSourceRegistry([$customDs], [$provider], $this->doctrineFactory);

        $result = $registry->all();

        $this->assertCount(1, $result);
        $this->assertSame($customDs, $result['AuditLog']); // Custom wins
    }

    public function testAllCachesResults(): void
    {
        $doctrineDs = $this->createMock(DoctrineDataSource::class);
        $doctrineDs->method('getIdentifier')->willReturn('Product');

        $this->doctrineFactory->expects($this->once())
            ->method('createAll')
            ->willReturn([$doctrineDs]);

        $registry = new DataSourceRegistry([], [], $this->doctrineFactory);

        // Call multiple times
        $registry->all();
        $registry->all();
        $registry->all();

        // Factory should only be called once (cached)
    }

    public function testGetReturnsDataSourceByIdentifier(): void
    {
        $doctrineDs = $this->createMock(DoctrineDataSource::class);
        $doctrineDs->method('getIdentifier')->willReturn('Product');

        $this->doctrineFactory->method('createAll')->willReturn([$doctrineDs]);

        $registry = new DataSourceRegistry([], [], $this->doctrineFactory);

        $result = $registry->get('Product');

        $this->assertSame($doctrineDs, $result);
    }

    public function testGetReturnsNullForNonExistentIdentifier(): void
    {
        $this->doctrineFactory->method('createAll')->willReturn([]);

        $registry = new DataSourceRegistry([], [], $this->doctrineFactory);

        $this->assertNull($registry->get('NonExistent'));
    }

    public function testHasReturnsTrueForExistingIdentifier(): void
    {
        $doctrineDs = $this->createMock(DoctrineDataSource::class);
        $doctrineDs->method('getIdentifier')->willReturn('Product');

        $this->doctrineFactory->method('createAll')->willReturn([$doctrineDs]);

        $registry = new DataSourceRegistry([], [], $this->doctrineFactory);

        $this->assertTrue($registry->has('Product'));
    }

    public function testHasReturnsFalseForNonExistentIdentifier(): void
    {
        $this->doctrineFactory->method('createAll')->willReturn([]);

        $registry = new DataSourceRegistry([], [], $this->doctrineFactory);

        $this->assertFalse($registry->has('NonExistent'));
    }

    public function testGetIdentifiersReturnsAllKeys(): void
    {
        $doctrineDs = $this->createMock(DoctrineDataSource::class);
        $doctrineDs->method('getIdentifier')->willReturn('Product');

        $this->doctrineFactory->method('createAll')->willReturn([$doctrineDs]);

        $customDs = $this->createMock(DataSourceInterface::class);
        $customDs->method('getIdentifier')->willReturn('CustomSource');

        $registry = new DataSourceRegistry([$customDs], [], $this->doctrineFactory);

        $identifiers = $registry->getIdentifiers();

        $this->assertCount(2, $identifiers);
        $this->assertContains('Product', $identifiers);
        $this->assertContains('CustomSource', $identifiers);
    }

    public function testClearCacheResetsCache(): void
    {
        $doctrineDs1 = $this->createMock(DoctrineDataSource::class);
        $doctrineDs1->method('getIdentifier')->willReturn('Product');

        $doctrineDs2 = $this->createMock(DoctrineDataSource::class);
        $doctrineDs2->method('getIdentifier')->willReturn('Category');

        $this->doctrineFactory->expects($this->exactly(2))
            ->method('createAll')
            ->willReturnOnConsecutiveCalls([$doctrineDs1], [$doctrineDs2]);

        $this->doctrineFactory->expects($this->once())
            ->method('clearCache');

        $registry = new DataSourceRegistry([], [], $this->doctrineFactory);

        // First call - gets Product
        $this->assertArrayHasKey('Product', $registry->all());

        // Clear cache
        $registry->clearCache();

        // Second call - gets Category
        $this->assertArrayHasKey('Category', $registry->all());
    }

    public function testMultipleProvidersAreMerged(): void
    {
        $this->doctrineFactory->method('createAll')->willReturn([]);

        $ds1 = $this->createMock(DataSourceInterface::class);
        $ds1->method('getIdentifier')->willReturn('AuditLog1');

        $ds2 = $this->createMock(DataSourceInterface::class);
        $ds2->method('getIdentifier')->willReturn('AuditLog2');

        $provider1 = $this->createMock(DataSourceProviderInterface::class);
        $provider1->method('getDataSources')->willReturn([$ds1]);

        $provider2 = $this->createMock(DataSourceProviderInterface::class);
        $provider2->method('getDataSources')->willReturn([$ds2]);

        $registry = new DataSourceRegistry([], [$provider1, $provider2], $this->doctrineFactory);

        $result = $registry->all();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('AuditLog1', $result);
        $this->assertArrayHasKey('AuditLog2', $result);
    }
}
