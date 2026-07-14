<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Service;

use Kachnitel\AdminBundle\Attribute\ColumnFilter;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use Kachnitel\AdminBundle\Service\PropertyFilterabilityService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
// use Kachnitel\AdminBundle\Tests\Service\Fixtures\EntityWithFilterDisabled;
// use Kachnitel\AdminBundle\Tests\Service\Fixtures\EntityWithFilterEnabled;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(PropertyFilterabilityService::class)]
#[UsesClass(ColumnFilter::class)]
#[Group('property-filterability')]
#[AllowMockObjectsWithoutExpectations]
final class PropertyFilterabilityServiceTest extends TestCase
{
    /** @var FilterMetadataProvider&MockObject */
    private FilterMetadataProvider $filterMetadataProvider;

    protected function setUp(): void
    {
        $this->filterMetadataProvider = $this->createMock(FilterMetadataProvider::class);
    }

    // ── getFilterConfig / isFilterable ─────────────────────────────────────────

    #[Test]
    public function testGetFilterConfigDelegatesToProvider(): void
    {
        $config = ['type' => 'text', 'operator' => 'LIKE'];

        $this->filterMetadataProvider
            ->expects($this->once())
            ->method('getFilterForProperty')
            ->with('App\Entity\Product', 'name')
            ->willReturn($config);

        $service = $this->makeService();

        $this->assertSame($config, $service->getFilterConfig('App\Entity\Product', 'name'));
    }

    #[Test]
    public function testGetFilterConfigReturnsNullWhenProviderReturnsNull(): void
    {
        $this->filterMetadataProvider
            ->method('getFilterForProperty')
            ->willReturn(null);

        $this->assertNull($this->makeService()->getFilterConfig('App\Entity\Product', 'internalCode'));
    }

    #[Test]
    public function testIsFilterableReturnsTrueWhenConfigExists(): void
    {
        $this->filterMetadataProvider
            ->method('getFilterForProperty')
            ->willReturn(['type' => 'text']);

        $this->assertTrue($this->makeService()->isFilterable('App\Entity\Product', 'name'));
    }

    #[Test]
    public function testIsFilterableReturnsFalseWhenConfigIsNull(): void
    {
        $this->filterMetadataProvider
            ->method('getFilterForProperty')
            ->willReturn(null);

        $this->assertFalse($this->makeService()->isFilterable('App\Entity\Product', 'name'));
    }

    // ── isExplicitlyDisabled ───────────────────────────────────────────────────

    #[Test]
    public function testIsExplicitlyDisabledReturnsFalseForUnknownClass(): void
    {
        // @phpstan-ignore argument.type
        $this->assertFalse($this->makeService()->isExplicitlyDisabled('NonExistentClass9999', 'field'));
    }

    #[Test]
    public function testIsExplicitlyDisabledReturnsFalseWhenPropertyHasNoAttribute(): void
    {
        // stdClass has no ColumnFilter attribute anywhere
        $this->assertFalse($this->makeService()->isExplicitlyDisabled(\stdClass::class, 'nonexistent'));
    }

    #[Test]
    public function testIsExplicitlyDisabledReturnsTrueForDisabledAttribute(): void
    {
        $this->assertTrue($this->makeService()->isExplicitlyDisabled(EntityWithFilterDisabled::class, 'field'));
    }

    #[Test]
    public function testIsExplicitlyDisabledReturnsFalseForEnabledAttribute(): void
    {
        $this->assertFalse($this->makeService()->isExplicitlyDisabled(EntityWithFilterEnabled::class, 'field'));
    }

    // ── buildCollectionFilterEntry ─────────────────────────────────────────────

    #[Test]
    public function testBuildCollectionFilterEntryReturnsNullWhenEntityHasNoGetId(): void
    {
        $entity = new \stdClass();

        $this->assertNull($this->makeService()->buildCollectionFilterEntry($entity, 'product', 'App\Entity\Tag'));
    }

    #[Test]
    public function testBuildCollectionFilterEntryReturnsNullWhenIdIsNull(): void
    {
        $entity = $this->entityWithId(null);

        $this->assertNull($this->makeService()->buildCollectionFilterEntry($entity, 'product', 'App\Entity\Tag'));
    }

    #[Test]
    public function testBuildCollectionFilterEntryReturnsNullWhenPropertyNotFilterable(): void
    {
        $this->filterMetadataProvider
            ->method('getFilterForProperty')
            ->willReturn(null);

        $entity = $this->entityWithId(5);

        $this->assertNull($this->makeService()->buildCollectionFilterEntry($entity, 'product', 'App\Entity\Tag'));
    }

    #[Test]
    public function testBuildCollectionFilterEntryReturnsFieldValuePairWhenFilterable(): void
    {
        $this->filterMetadataProvider
            ->expects($this->once())->method('getFilterForProperty')
            ->with('App\Entity\Tag', 'product')
            ->willReturn(['type' => 'relation']);

        $entity = $this->entityWithId(42);

        $result = $this->makeService()->buildCollectionFilterEntry($entity, 'product', 'App\Entity\Tag');

        $this->assertSame(['product' => '42'], $result);
    }

    #[Test]
    public function testBuildCollectionFilterEntryCastsIdToString(): void
    {
        $this->filterMetadataProvider
            ->method('getFilterForProperty')
            ->willReturn(['type' => 'relation']);

        $entity = $this->entityWithId(7);

        $result = $this->makeService()->buildCollectionFilterEntry($entity, 'owner', 'App\Entity\Order');

        $this->assertIsString($result['owner'] ?? null);
        $this->assertSame('7', $result['owner']);
    }

    // ── debug mode ─────────────────────────────────────────────────────────────

    #[Test]
    public function testBuildCollectionFilterEntryDoesNotThrowInNonDebugModeEvenWhenDisabled(): void
    {
        $this->filterMetadataProvider->method('getFilterForProperty')->willReturn(null);

        $entity  = $this->entityWithId(1);
        $service = $this->makeService(debug: false);

        // Must not throw, must return null
        $this->assertNull($service->buildCollectionFilterEntry($entity, 'product', 'App\Entity\Tag'));
    }

    #[Test]
    public function testBuildCollectionFilterEntryThrowsInDebugModeWhenExplicitlyDisabled(): void
    {
        $this->filterMetadataProvider->method('getFilterForProperty')->willReturn(null);

        $entityClass = EntityWithFilterDisabled::class;
        $entity      = $this->entityWithId(1);
        $service     = $this->makeService(debug: true);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/enabled: false/');

        $service->buildCollectionFilterEntry($entity, 'field', $entityClass);
    }

    #[Test]
    public function testBuildCollectionFilterEntryDoesNotThrowInDebugModeWhenNotExplicitlyDisabled(): void
    {
        // No attribute at all → not explicitly disabled → no throw, just returns null
        $this->filterMetadataProvider->method('getFilterForProperty')->willReturn(null);

        $entity  = $this->entityWithId(1);
        $service = $this->makeService(debug: true);

        $this->assertNull($service->buildCollectionFilterEntry($entity, 'unknownField', \stdClass::class));
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function makeService(bool $debug = false): PropertyFilterabilityService
    {
        return new PropertyFilterabilityService(
            filterMetadataProvider: $this->filterMetadataProvider,
            debug: $debug,
        );
    }

    private function entityWithId(mixed $id): object
    {
        return new class ($id) {
            public function __construct(private readonly mixed $id) {}
            public function getId(): mixed { return $this->id; }
        };
    }
}

class EntityWithFilterDisabled
{
    #[ColumnFilter(enabled: false)]
    public string $field = '';
}

class EntityWithFilterEnabled
{
    public string $field = '';
}
