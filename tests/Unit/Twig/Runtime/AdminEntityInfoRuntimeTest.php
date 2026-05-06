<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Twig\Runtime\AdminEntityInfoRuntime;
use Kachnitel\AdminBundle\Utils\ObjectHelper;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Kachnitel\AdminBundle\Twig\Runtime\AdminEntityInfoRuntime
 */
#[UsesClass(ObjectHelper::class)]
#[UsesClass(AdminColumn::class)]
class AdminEntityInfoRuntimeTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var ClassMetadata<object>&MockObject */
    private ClassMetadata $metadata;

    /** @var AttributeHelper&MockObject */
    private AttributeHelper $attributeHelper;

    private AdminEntityInfoRuntime $runtime;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->metadata = $this->createMock(ClassMetadata::class);
        $this->attributeHelper = $this->createMock(AttributeHelper::class);

        $this->em->method('getClassMetadata')->willReturn($this->metadata);

        $this->runtime = new AdminEntityInfoRuntime(
            attributeHelper: $this->attributeHelper,
            em: $this->em,
        );
    }

    // ── getColumnTemplates ────────────────────────────────────────────────────

    /** @test */
    public function getColumnTemplatesForDoctrineEntityReturnsEntitySpecificPathFirst(): void
    {
        $templates = $this->runtime->getColumnTemplates(
            dataSourceId: null,
            entityClass: 'App\\Entity\\Product',
            column: 'price',
            columnType: 'decimal',
        );

        $this->assertStringContainsString('App/Entity/Product/price.html.twig', $templates[0]);
    }

    /** @test */
    public function getColumnTemplatesIncludesTypeSpecificTemplate(): void
    {
        $templates = $this->runtime->getColumnTemplates(
            dataSourceId: null,
            entityClass: 'App\\Entity\\Product',
            column: 'createdAt',
            columnType: 'datetime',
        );

        $typeTemplate = array_values(array_filter($templates, fn ($t) => str_contains($t, 'datetime/_preview')));
        $this->assertNotEmpty($typeTemplate, 'A datetime type-specific template should be in the list');
    }

    /** @test */
    public function getColumnTemplatesAlwaysIncludesFallbackTemplate(): void
    {
        $templates = $this->runtime->getColumnTemplates(
            dataSourceId: null,
            entityClass: 'App\\Entity\\Product',
            column: 'name',
            columnType: 'string',
        );

        $last = end($templates);
        $this->assertNotFalse($last, 'Templates array should not be empty');
        $this->assertStringContainsString('types/_preview.html.twig', $last);
    }

    /** @test */
    public function getColumnTemplatesForDataSourceUsesDataSourcePath(): void
    {
        $templates = $this->runtime->getColumnTemplates(
            dataSourceId: 'audit-log',
            entityClass: null,
            column: 'action',
            columnType: 'string',
        );

        $this->assertStringContainsString('types/data/audit-log/action', $templates[0]);
    }

    /** @test */
    public function getColumnTemplatesForCollectionUsesCollectionTemplateName(): void
    {
        $templates = $this->runtime->getColumnTemplates(
            dataSourceId: null,
            entityClass: 'App\\Entity\\Order',
            column: 'lineItems',
            columnType: 'App\\Entity\\LineItem',
            isCollection: true,
        );

        // Entity-specific path has no template suffix
        $this->assertStringContainsString('App/Entity/Order/lineItems.html.twig', $templates[0]);
        // Type-specific path includes collection suffix
        $typeSpecificTemplate = array_values(array_filter($templates, fn ($t) => str_contains($t, 'LineItem/_collection')))[0] ?? null;
        $this->assertNotNull($typeSpecificTemplate, 'A collection-specific template should be in the list');
    }

    // ── getEntityColumnTemplates ──────────────────────────────────────────────

    /** @test */
    public function getEntityColumnTemplatesReturnsCorrectTemplatesForField(): void
    {
        $entity = new \stdClass();
        $entity->name = 'test';

        $this->metadata->method('isCollectionValuedAssociation')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getTypeOfField')->with('name')->willReturn('string');

        $templates = $this->runtime->getEntityColumnTemplates($entity, 'name');

        $this->assertNotEmpty($templates);
        $this->assertStringContainsString('stdClass/name', $templates[0]);
    }

    /** @test */
    public function getEntityColumnTemplatesUsesCollectionTemplateForCollection(): void
    {
        $entity = new \stdClass();
        $this->metadata->method('isCollectionValuedAssociation')->with('items')->willReturn(true);
        $this->metadata->method('hasAssociation')->with('items')->willReturn(true);
        $this->metadata->method('getAssociationTargetClass')->with('items')->willReturn('App\\Entity\\Item');

        $templates = $this->runtime->getEntityColumnTemplates($entity, 'items');

        // Entity-specific path has no template suffix
        $this->assertStringContainsString('stdClass/items.html.twig', $templates[0]);
        // Type-specific path includes collection suffix
        $typeSpecificTemplate = array_values(array_filter($templates, fn ($t) => str_contains($t, 'Item/_collection')))[0] ?? null;
        $this->assertNotNull($typeSpecificTemplate, 'A collection-specific template should be in the list');
    }

    // ── getFieldComponentName ─────────────────────────────────────────────────

    /** @test */
    public function getFieldComponentNameReturnsStringFieldForStringType(): void
    {
        $entity = new \stdClass();
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('hasField')->with('title')->willReturn(true);
        $this->metadata->method('getTypeOfField')->with('title')->willReturn('string');
        $this->metadata->method('getFieldMapping')->willReturn(
            new \Doctrine\ORM\Mapping\FieldMapping(fieldName: 'title', type: 'string', columnName: 'title')
        );

        $result = $this->runtime->getFieldComponentName($entity, 'title');

        $this->assertSame('K:Entity:Field:String', $result);
    }

    /** @test */
    public function getFieldComponentNameReturnsIntFieldForIntegerType(): void
    {
        $entity = new \stdClass();
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('hasField')->with('quantity')->willReturn(true);
        $this->metadata->method('getTypeOfField')->with('quantity')->willReturn('integer');
        $this->metadata->method('getFieldMapping')->willReturn(
            new \Doctrine\ORM\Mapping\FieldMapping(fieldName: 'quantity', type: 'integer', columnName: 'quantity')
        );

        $result = $this->runtime->getFieldComponentName($entity, 'quantity');

        $this->assertSame('K:Entity:Field:Int', $result);
    }

    /** @test */
    public function getFieldComponentNameReturnsFloatFieldForDecimalType(): void
    {
        $entity = new \stdClass();
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('hasField')->with('price')->willReturn(true);
        $this->metadata->method('getTypeOfField')->with('price')->willReturn('decimal');
        $this->metadata->method('getFieldMapping')->willReturn(
            new \Doctrine\ORM\Mapping\FieldMapping(fieldName: 'price', type: 'decimal', columnName: 'price')
        );

        $result = $this->runtime->getFieldComponentName($entity, 'price');

        $this->assertSame('K:Entity:Field:Float', $result);
    }

    /** @test */
    public function getFieldComponentNameReturnsBoolFieldForBooleanType(): void
    {
        $entity = new \stdClass();
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('hasField')->with('active')->willReturn(true);
        $this->metadata->method('getTypeOfField')->with('active')->willReturn('boolean');
        $this->metadata->method('getFieldMapping')->willReturn(
            new \Doctrine\ORM\Mapping\FieldMapping(fieldName: 'active', type: 'boolean', columnName: 'active')
        );

        $result = $this->runtime->getFieldComponentName($entity, 'active');

        $this->assertSame('K:Entity:Field:Bool', $result);
    }

    /** @test */
    public function getFieldComponentNameReturnsDateFieldForDatetimeType(): void
    {
        $entity = new \stdClass();
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('hasField')->with('createdAt')->willReturn(true);
        $this->metadata->method('getTypeOfField')->with('createdAt')->willReturn('datetime');
        $this->metadata->method('getFieldMapping')->willReturn(
            new \Doctrine\ORM\Mapping\FieldMapping(fieldName: 'createdAt', type: 'datetime', columnName: 'created_at')
        );

        $result = $this->runtime->getFieldComponentName($entity, 'createdAt');

        $this->assertSame('K:Entity:Field:Date', $result);
    }

    /** @test */
    public function getFieldComponentNameReturnsRelationshipForSingleValuedAssociation(): void
    {
        $entity = new \stdClass();
        $this->metadata->method('hasAssociation')->with('category')->willReturn(true);
        $this->metadata->method('isSingleValuedAssociation')->with('category')->willReturn(true);

        $result = $this->runtime->getFieldComponentName($entity, 'category');

        $this->assertSame('K:Entity:Field:Relationship', $result);
    }

    /** @test */
    public function getFieldComponentNameReturnsCollectionForCollectionValuedAssociation(): void
    {
        $entity = new \stdClass();
        $this->metadata->method('hasAssociation')->with('tags')->willReturn(true);
        $this->metadata->method('isSingleValuedAssociation')->with('tags')->willReturn(false);

        $result = $this->runtime->getFieldComponentName($entity, 'tags');

        $this->assertSame('K:Entity:Field:Collection', $result);
    }

    /** @test */
    public function getFieldComponentNameReturnsNullForUnmappedProperty(): void
    {
        $entity = new \stdClass();
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('hasField')->willReturn(false);

        $result = $this->runtime->getFieldComponentName($entity, 'virtualField');

        $this->assertNull($result);
    }

    // ── getColumnAttribute ────────────────────────────────────────────────────

    /** @test */
    public function getColumnAttributeReturnsAttributeWhenPresent(): void
    {
        $entity = new \stdClass();
        $attr = new AdminColumn(editable: true);

        $this->attributeHelper->method('getPropertyAttribute')
            ->with($entity, 'title', AdminColumn::class)
            ->willReturn($attr);

        $result = $this->runtime->getColumnAttribute($entity, 'title');

        $this->assertSame($attr, $result);
    }

    /** @test */
    public function getColumnAttributeReturnsNullWhenAttributeAbsent(): void
    {
        $entity = new \stdClass();

        $this->attributeHelper->method('getPropertyAttribute')->willReturn(null);

        $result = $this->runtime->getColumnAttribute($entity, 'name');

        $this->assertNull($result);
    }

    /** @test */
    public function getColumnAttributeReturnsNullOnReflectionException(): void
    {
        $entity = new \stdClass();

        $this->attributeHelper->method('getPropertyAttribute')
            ->willThrowException(new \ReflectionException('Property not found'));

        $result = $this->runtime->getColumnAttribute($entity, 'nonExistent');

        $this->assertNull($result);
    }
}
