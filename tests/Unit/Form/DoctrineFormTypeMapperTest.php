<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Form;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
use Kachnitel\AdminBundle\Form\DoctrineFormTypeMapper;
use Kachnitel\AdminBundle\Tests\Fixtures\TestStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;

/**
 * @group auto-form
 */
#[CoversClass(DoctrineFormTypeMapper::class)]
class DoctrineFormTypeMapperTest extends TestCase
{
    private DoctrineFormTypeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new DoctrineFormTypeMapper();
    }

    // ── Unsupported types ──────────────────────────────────────────────────────

    /**
     * @param non-empty-string $doctrineType
     */
    #[DataProvider('unsupportedTypeProvider')]
    public function testUnsupportedTypeReturnsNull(string $doctrineType): void
    {
        $metadata = $this->makeMetadata(['name' => ['type' => $doctrineType, 'nullable' => false]]);
        $this->assertNull($this->mapper->getFieldConfig($metadata, 'name'));
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public static function unsupportedTypeProvider(): array
    {
        return [
            'json'         => ['json'],
            'array'        => ['array'],
            'simple_array' => ['simple_array'],
            'object'       => ['object'],
            'blob'         => ['blob'],
            'binary'       => ['binary'],
        ];
    }

    // ── String / Text ──────────────────────────────────────────────────────────

    public function testStringFieldMapsToTextType(): void
    {
        $metadata = $this->makeMetadata(['name' => ['type' => 'string', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'name');

        $this->assertNotNull($config);
        $this->assertSame(TextType::class, $config['type']);
    }

    public function testStringRequiredWhenNotNullable(): void
    {
        $metadata = $this->makeMetadata(['name' => ['type' => 'string', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'name');

        $this->assertNotNull($config);
        $this->assertTrue($config['options']['required']);
    }

    public function testStringNotRequiredWhenNullable(): void
    {
        $metadata = $this->makeMetadata(['name' => ['type' => 'string', 'nullable' => true]]);
        $config = $this->mapper->getFieldConfig($metadata, 'name');

        $this->assertNotNull($config);
        $this->assertFalse($config['options']['required']);
    }

    public function testStringNonNullableHasEmptyDataString(): void
    {
        $metadata = $this->makeMetadata(['name' => ['type' => 'string', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'name');

        $this->assertNotNull($config);
        $this->assertSame('', $config['options']['empty_data']);
    }

    public function testStringNullableHasNullEmptyData(): void
    {
        $metadata = $this->makeMetadata(['name' => ['type' => 'string', 'nullable' => true]]);
        $config = $this->mapper->getFieldConfig($metadata, 'name');

        $this->assertNotNull($config);
        $this->assertNull($config['options']['empty_data']);
    }

    public function testTextFieldMapsToTextType(): void
    {
        $metadata = $this->makeMetadata(['body' => ['type' => 'text', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'body');

        $this->assertNotNull($config);
        $this->assertSame(TextareaType::class, $config['type']);
    }

    // ── Integer ────────────────────────────────────────────────────────────────

    /**
     * @param non-empty-string $doctrineType
     */
    #[DataProvider('integerTypeProvider')]
    public function testIntegerFieldMapsToIntegerType(string $doctrineType): void
    {
        $metadata = $this->makeMetadata(['count' => ['type' => $doctrineType, 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'count');

        $this->assertNotNull($config);
        $this->assertSame(IntegerType::class, $config['type']);
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public static function integerTypeProvider(): array
    {
        return [
            'integer'  => ['integer'],
            'smallint' => ['smallint'],
            'bigint'   => ['bigint'],
        ];
    }

    // ── Float / Decimal ────────────────────────────────────────────────────────

    /**
     * @param non-empty-string $doctrineType
     */
    #[DataProvider('numberTypeProvider')]
    public function testDecimalFloatFieldMapsToNumberType(string $doctrineType): void
    {
        $metadata = $this->makeMetadata(['price' => ['type' => $doctrineType, 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'price');

        $this->assertNotNull($config);
        $this->assertSame(NumberType::class, $config['type']);
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public static function numberTypeProvider(): array
    {
        return [
            'decimal' => ['decimal'],
            'float'   => ['float'],
        ];
    }

    // ── Boolean ────────────────────────────────────────────────────────────────

    public function testBooleanFieldMapsToCheckboxType(): void
    {
        $metadata = $this->makeMetadata(['active' => ['type' => 'boolean', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'active');

        $this->assertNotNull($config);
        $this->assertSame(CheckboxType::class, $config['type']);
    }

    public function testBooleanIsNeverRequired(): void
    {
        $metadata = $this->makeMetadata(['active' => ['type' => 'boolean', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'active');

        $this->assertNotNull($config);
        $this->assertFalse($config['options']['required']);
    }

    // ── Date / DateTime / Time ─────────────────────────────────────────────────

    /**
     * @param non-empty-string $doctrineType
     */
    #[DataProvider('dateTypeProvider')]
    public function testDateFieldMapsToDateType(string $doctrineType): void
    {
        $metadata = $this->makeMetadata(['dob' => ['type' => $doctrineType, 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'dob');

        $this->assertNotNull($config);
        $this->assertSame(DateType::class, $config['type']);
        $this->assertSame('single_text', $config['options']['widget']);
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public static function dateTypeProvider(): array
    {
        return [
            'date'           => ['date'],
            'date_immutable' => ['date_immutable'],
        ];
    }

    /**
     * @param non-empty-string $doctrineType
     */
    #[DataProvider('datetimeTypeProvider')]
    public function testDatetimeFieldMapsToDateTimeType(string $doctrineType): void
    {
        $metadata = $this->makeMetadata(['createdAt' => ['type' => $doctrineType, 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'createdAt');

        $this->assertNotNull($config);
        $this->assertSame(DateTimeType::class, $config['type']);
        $this->assertSame('single_text', $config['options']['widget']);
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public static function datetimeTypeProvider(): array
    {
        return [
            'datetime'              => ['datetime'],
            'datetime_immutable'    => ['datetime_immutable'],
            'datetimetz'            => ['datetimetz'],
            'datetimetz_immutable'  => ['datetimetz_immutable'],
        ];
    }

    /**
     * @param non-empty-string $doctrineType
     */
    #[DataProvider('timeTypeProvider')]
    public function testTimeFieldMapsToTimeType(string $doctrineType): void
    {
        $metadata = $this->makeMetadata(['startsAt' => ['type' => $doctrineType, 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'startsAt');

        $this->assertNotNull($config);
        $this->assertSame(TimeType::class, $config['type']);
        $this->assertSame('single_text', $config['options']['widget']);
    }

    /**
     * @return array<string, array{0: non-empty-string}>
     */
    public static function timeTypeProvider(): array
    {
        return [
            'time'           => ['time'],
            'time_immutable' => ['time_immutable'],
        ];
    }

    // ── Enum ───────────────────────────────────────────────────────────────────

    public function testBackedEnumFieldMapsToEnumType(): void
    {
        $metadata = $this->makeMetadata(
            ['status' => ['type' => 'string', 'nullable' => false]],
            ['status' => TestStatus::class]
        );
        $config = $this->mapper->getFieldConfig($metadata, 'status');

        $this->assertNotNull($config);
        $this->assertSame(EnumType::class, $config['type']);
        $this->assertSame(TestStatus::class, $config['options']['class']);
    }

    public function testNullableEnumHasPlaceholder(): void
    {
        $metadata = $this->makeMetadata(
            ['status' => ['type' => 'string', 'nullable' => true]],
            ['status' => TestStatus::class]
        );
        $config = $this->mapper->getFieldConfig($metadata, 'status');

        $this->assertNotNull($config);
        $this->assertSame('', $config['options']['placeholder']);
    }

    public function testNonNullableEnumHasNullPlaceholder(): void
    {
        $metadata = $this->makeMetadata(
            ['status' => ['type' => 'string', 'nullable' => false]],
            ['status' => TestStatus::class]
        );
        $config = $this->mapper->getFieldConfig($metadata, 'status');

        $this->assertNotNull($config);
        $this->assertFalse($config['options']['placeholder']);
    }

    // ── Label humanisation ─────────────────────────────────────────────────────

    // public function testLabelIsHumanised(): void
    // {
    //     $metadata = $this->makeMetadata(['firstName' => ['type' => 'string', 'nullable' => false]]);
    //     $config = $this->mapper->getFieldConfig($metadata, 'firstName');

    //     $this->assertNotNull($config);
    //     $this->assertSame('First name', $config['options']['label']);
    // }

    // ── Associations ───────────────────────────────────────────────────────────

    public function testAssociationConfigReturnsEntityType(): void
    {
        /** @var ClassMetadata<object>&\PHPUnit\Framework\MockObject\MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getAssociationTargetClass')->with('category')->willReturn('App\Entity\Category');
        $metadata->method('hasAssociation')->with('category')->willReturn(true);
        $metadata->method('isSingleValuedAssociation')->with('category')->willReturn(true);

        $config = $this->mapper->getAssociationConfig($metadata, 'category');

        $this->assertNotNull($config);
        $this->assertSame(EntityType::class, $config['type']);
        $this->assertSame('App\Entity\Category', $config['options']['class']);
    }

    public function testAssociationIsNotRequired(): void
    {
        /** @var ClassMetadata<object>&\PHPUnit\Framework\MockObject\MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getAssociationTargetClass')->willReturn('App\Entity\Category');
        $metadata->method('hasAssociation')->with('category')->willReturn(true);
        $metadata->method('isSingleValuedAssociation')->with('category')->willReturn(true);

        $config = $this->mapper->getAssociationConfig($metadata, 'category');

        $this->assertNotNull($config);
        $this->assertFalse($config['options']['required']);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Build a stub ClassMetadata with the given field mapping.
     *
     * @param array<string, array{type: string, nullable: bool}> $fields
     * @param array<string, class-string<\BackedEnum>>           $enumTypes
     * @return ClassMetadata<object>
     */
    private function makeMetadata(array $fields, array $enumTypes = []): ClassMetadata
    {
        /** @var ClassMetadata<object>&\PHPUnit\Framework\MockObject\MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);

        $metadata->method('getFieldMapping')
            ->willReturnCallback(function (string $field) use ($fields, $enumTypes): FieldMapping {
                $data    = $fields[$field] ?? ['type' => 'string', 'nullable' => false];
                $mapping = new FieldMapping(
                    type: $data['type'],
                    fieldName: $field,
                    columnName: $field,
                );
                $mapping->nullable  = $data['nullable'];
                $mapping->enumType  = $enumTypes[$field] ?? null;

                return $mapping;
            });
        $metadata->method('hasField')
            ->willReturnCallback(fn (string $field) => isset($fields[$field]));

        return $metadata;
    }
}
