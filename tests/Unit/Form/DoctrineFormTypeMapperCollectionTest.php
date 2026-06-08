<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Form;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToManyOwningSideMapping;
use Doctrine\ORM\Mapping\OneToManyAssociationMapping;
use Kachnitel\AdminBundle\Form\DoctrineFormTypeMapper;
use Kachnitel\AdminBundle\Form\DynamicEntityFormType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\UX\LiveComponent\Form\Type\LiveCollectionType;

/**
 * @covers \Kachnitel\AdminBundle\Form\DoctrineFormTypeMapper
 * @group dynamic-form
 * @group collections
 */
#[CoversClass(DoctrineFormTypeMapper::class)]
#[Group('dynamic-form')]
#[Group('collections')]
class DoctrineFormTypeMapperCollectionTest extends TestCase
{
    /** @var ClassMetadata<object>&MockObject */
    private ClassMetadata $metadata;

    private DoctrineFormTypeMapper $mapper;

    protected function setUp(): void
    {
        $this->metadata = $this->createMock(ClassMetadata::class);
        $this->mapper   = new DoctrineFormTypeMapper();
    }

    // ── ManyToMany → EntityType with multiple: true ────────────────────────────

    /** @test */
    public function manyToManyAssociationReturnsEntityTypeWithMultiple(): void
    {
        $mapping = new ManyToManyOwningSideMapping('tags', 'App\Entity\Product', 'App\Entity\Tag');

        $this->metadata->method('hasAssociation')->with('tags')->willReturn(true);
        $this->metadata->method('isSingleValuedAssociation')->with('tags')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->with('tags')->willReturn(true);
        $this->metadata->method('getAssociationMapping')->with('tags')->willReturn($mapping);
        $this->metadata->method('getAssociationTargetClass')->with('tags')->willReturn('App\Entity\Tag');

        $config = $this->mapper->getAssociationConfig($this->metadata, 'tags');

        $this->assertNotNull($config);
        $this->assertSame(EntityType::class, $config['type']);
        $this->assertTrue($config['options']['multiple']);
        $this->assertSame('App\Entity\Tag', $config['options']['class']);
    }

    /** @test */
    public function manyToManyAssociationIsNotRequired(): void
    {
        $mapping = new ManyToManyOwningSideMapping('tags', 'App\Entity\Product', 'App\Entity\Tag');

        $this->metadata->method('hasAssociation')->with('tags')->willReturn(true);
        $this->metadata->method('isSingleValuedAssociation')->with('tags')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->with('tags')->willReturn(true);
        $this->metadata->method('getAssociationMapping')->with('tags')->willReturn($mapping);
        $this->metadata->method('getAssociationTargetClass')->with('tags')->willReturn('App\Entity\Tag');

        $config = $this->mapper->getAssociationConfig($this->metadata, 'tags');

        $this->assertNotNull($config);
        $this->assertFalse($config['options']['required']);
    }

    // ── OneToMany → LiveCollectionType ─────────────────────────────────────────

    /** @test */
    public function oneToManyAssociationReturnsLiveCollectionType(): void
    {
        $mapping = new OneToManyAssociationMapping('items', 'App\Entity\Order', 'App\Entity\OrderItem');
        $mapping->mappedBy = 'order';

        $this->metadata->method('hasAssociation')->with('items')->willReturn(true);
        $this->metadata->method('isSingleValuedAssociation')->with('items')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->with('items')->willReturn(true);
        $this->metadata->method('getAssociationMapping')->with('items')->willReturn($mapping);
        $this->metadata->method('getAssociationTargetClass')->with('items')->willReturn('App\Entity\OrderItem');

        $config = $this->mapper->getAssociationConfig($this->metadata, 'items');

        $this->assertNotNull($config);
        $this->assertSame(LiveCollectionType::class, $config['type']);
    }

    /** @test */
    public function oneToManyUsesRecursiveDynamicEntityFormTypeAsEntryType(): void
    {
        $mapping = new OneToManyAssociationMapping('items', 'App\Entity\Order', 'App\Entity\OrderItem');
        $mapping->mappedBy = 'order';

        $this->metadata->method('hasAssociation')->with('items')->willReturn(true);
        $this->metadata->method('isSingleValuedAssociation')->with('items')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->with('items')->willReturn(true);
        $this->metadata->method('getAssociationMapping')->with('items')->willReturn($mapping);
        $this->metadata->method('getAssociationTargetClass')->with('items')->willReturn('App\Entity\OrderItem');

        $config = $this->mapper->getAssociationConfig($this->metadata, 'items');

        $this->assertNotNull($config);
        $this->assertSame(DynamicEntityFormType::class, $config['options']['entry_type']);
    }

    /** @test */
    public function oneToManyPassesTargetClassInEntryOptions(): void
    {
        $mapping = new OneToManyAssociationMapping('items', 'App\Entity\Order', 'App\Entity\OrderItem');
        $mapping->mappedBy = 'order';

        $this->metadata->method('hasAssociation')->with('items')->willReturn(true);
        $this->metadata->method('isSingleValuedAssociation')->with('items')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->with('items')->willReturn(true);
        $this->metadata->method('getAssociationMapping')->with('items')->willReturn($mapping);
        $this->metadata->method('getAssociationTargetClass')->with('items')->willReturn('App\Entity\OrderItem');

        $config = $this->mapper->getAssociationConfig($this->metadata, 'items');

        $this->assertNotNull($config);
        $entryOptions = $config['options']['entry_options'];
        $this->assertSame('App\Entity\OrderItem', $entryOptions['entity_class']);
        $this->assertSame('App\Entity\OrderItem', $entryOptions['data_class']);
    }

    /** @test */
    public function oneToManyEntryOptionsMarkAsChildForm(): void
    {
        $mapping = new OneToManyAssociationMapping('items', 'App\Entity\Order', 'App\Entity\OrderItem');
        $mapping->mappedBy = 'order';

        $this->metadata->method('hasAssociation')->with('items')->willReturn(true);
        $this->metadata->method('isSingleValuedAssociation')->with('items')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->with('items')->willReturn(true);
        $this->metadata->method('getAssociationMapping')->with('items')->willReturn($mapping);
        $this->metadata->method('getAssociationTargetClass')->with('items')->willReturn('App\Entity\OrderItem');

        $config = $this->mapper->getAssociationConfig($this->metadata, 'items');

        $this->assertNotNull($config);
        // is_root: false prevents infinite recursion in child forms
        $this->assertFalse($config['options']['entry_options']['is_root']);
    }

    /** @test */
    public function oneToManyAllowsAddAndDelete(): void
    {
        $mapping = new OneToManyAssociationMapping('items', 'App\Entity\Order', 'App\Entity\OrderItem');
        $mapping->mappedBy = 'order';

        $this->metadata->method('hasAssociation')->with('items')->willReturn(true);
        $this->metadata->method('isSingleValuedAssociation')->with('items')->willReturn(false);
        $this->metadata->method('isCollectionValuedAssociation')->with('items')->willReturn(true);
        $this->metadata->method('getAssociationMapping')->with('items')->willReturn($mapping);
        $this->metadata->method('getAssociationTargetClass')->with('items')->willReturn('App\Entity\OrderItem');

        $config = $this->mapper->getAssociationConfig($this->metadata, 'items');

        $this->assertNotNull($config);
        $this->assertTrue($config['options']['allow_add']);
        $this->assertTrue($config['options']['allow_delete']);
    }

    // ── Existing single-valued associations still work ─────────────────────────

    /** @test */
    public function singleValuedAssociationStillReturnsEntityType(): void
    {
        $this->metadata->method('hasAssociation')->with('category')->willReturn(true);
        $this->metadata->method('isSingleValuedAssociation')->with('category')->willReturn(true);
        $this->metadata->method('isCollectionValuedAssociation')->with('category')->willReturn(false);
        $this->metadata->method('getAssociationTargetClass')->with('category')->willReturn('App\Entity\Category');

        $config = $this->mapper->getAssociationConfig($this->metadata, 'category');

        $this->assertNotNull($config);
        $this->assertSame(EntityType::class, $config['type']);
        $this->assertArrayNotHasKey('multiple', $config['options']);
    }

    /** @test */
    public function nonExistentAssociationReturnsNull(): void
    {
        $this->metadata->method('hasAssociation')->with('nonexistent')->willReturn(false);

        $config = $this->mapper->getAssociationConfig($this->metadata, 'nonexistent');

        $this->assertNull($config);
    }
}
