<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use Kachnitel\AdminBundle\Service\Traits\FieldFilterConfigTrait;
use Kachnitel\AdminBundle\Utils\Text;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[PHPUnit\CoversMethod(FilterMetadataProvider::class, 'getFilterForProperty')]
#[PHPUnit\UsesClass(Text::class)]
#[PHPUnit\UsesClass(ColumnFilter::class)]
#[PHPUnit\UsesClass(FilterMetadataProvider::class)]
#[PHPUnit\UsesTrait(FieldFilterConfigTrait::class)]
#[PHPUnit\Group('collection-url')]
#[PHPUnit\AllowMockObjectsWithoutExpectations]
final class FilterMetadataProviderGetFilterForPropertyTest extends TestCase
{
    /** @var ClassMetadata<object>&MockObject */
    private ClassMetadata $metadata;

    private FilterMetadataProvider $provider;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $this->metadata = $metadata;

        $em->method('getClassMetadata')->willReturn($this->metadata);

        $this->provider = new FilterMetadataProvider($em);
    }

    #[PHPUnit\Test]
    public function testReturnsNullForNonExistentProperty(): void
    {
        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(false);

        $result = $this->provider->getFilterForProperty(
            FilterPropertyEntity::class,
            'nonExistentProperty'
        );

        $this->assertNull($result);
    }

    #[PHPUnit\Test]
    public function testReturnsNullWhenFilteringExplicitlyDisabled(): void
    {
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(false);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $result = $this->provider->getFilterForProperty(
            FilterPropertyEntity::class,
            'disabledField'
        );

        $this->assertNull($result);
    }

    #[PHPUnit\Test]
    public function testReturnsFilterConfigForFieldWithExplicitAttribute(): void
    {
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(false);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $result = $this->provider->getFilterForProperty(
            FilterPropertyEntity::class,
            'namedField'
        );

        $this->assertIsArray($result);
        $this->assertSame('text', $result['type']);
    }

    #[PHPUnit\Test]
    public function testReturnsAutoDetectedFilterConfigForFieldWithNoAttribute(): void
    {
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(false);
        $this->metadata->method('getTypeOfField')->willReturn('string');

        $result = $this->provider->getFilterForProperty(
            FilterPropertyEntity::class,
            'bareField'
        );

        $this->assertIsArray($result);
        $this->assertSame('text', $result['type']);
        $this->assertTrue($result['enabled']);
    }

    #[PHPUnit\Test]
    public function testReturnsNullForCollectionAssociationWithoutAttribute(): void
    {
        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(true);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(true);

        $result = $this->provider->getFilterForProperty(
            FilterPropertyEntity::class,
            'bareCollectionField'
        );

        // Collection associations are opt-in — no attribute means not filterable
        $this->assertNull($result);
    }
}

// ── Inline fixtures ────────────────────────────────────────────────────────────

/**
 * Inline fixture class carrying the various ColumnFilter scenarios.
 * Uses real attributes so ReflectionProperty reads them correctly.
 */
class FilterPropertyEntity
{
    /** Auto-detected (no attribute) */
    public string $bareField = '';

    /**
     * Auto-detected collection (no attribute — opt-in required)
     *
     * @var array<int, string>
     */
    public array $bareCollectionField = [];

    /** Explicitly named filter */
    #[ColumnFilter(type: ColumnFilter::TYPE_TEXT)]
    public string $namedField = '';

    /** Explicitly disabled */
    #[ColumnFilter(enabled: false)]
    public string $disabledField = '';
}
