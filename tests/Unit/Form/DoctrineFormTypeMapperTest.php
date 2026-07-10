<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Form;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
use Kachnitel\AdminBundle\Tests\Fixtures\TestStatus;
use Kachnitel\DynamicFormBundle\Form\DoctrineFormTypeMapper;
use Kachnitel\DynamicFormBundle\Form\Exception\NullabilityMismatchException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
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
use Symfony\Component\Validator\Constraints\NotBlank;

/** @deprecated (DoctrineFormTypeMapper is now a part of dynamic-form-bundle) */
#[CoversClass(DoctrineFormTypeMapper::class)]
#[UsesClass(NullabilityMismatchException::class)]
#[Group('auto-form')]
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
    #[Test]
    #[DataProvider('unsupportedTypeProvider')]    public function itReturnsNullForUnsupportedType(string $doctrineType): void
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
    //
    // Untouched by the empty_data unification below — string/text keep their
    // original, unrelated behaviour: null when nullable, '' when required.
    // TextType has no transformer to trip over a literal null, so there was
    // never a bug to fix here. See DoctrineFormTypeMapper's class docblock.

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

    public function testStringNullableUsesEmptyStringEmptyData(): void
    {
        $metadata = $this->makeMetadata(['name' => ['type' => 'string', 'nullable' => true]]);
        $config = $this->mapper->getFieldConfig($metadata, 'name');

        $this->assertNotNull($config);
        $this->assertSame('', $config['options']['empty_data']);
    }

    public function testTextFieldMapsToTextareaType(): void
    {
        $metadata = $this->makeMetadata(['body' => ['type' => 'text', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'body');

        $this->assertNotNull($config);
        $this->assertSame(TextareaType::class, $config['type']);
    }

    public function testTextNullableHasEmptyStringEmptyData(): void
    {
        $metadata = $this->makeMetadata(['body' => ['type' => 'text', 'nullable' => true]]);
        $config = $this->mapper->getFieldConfig($metadata, 'body');

        $this->assertNotNull($config);
        $this->assertSame('', $config['options']['empty_data']);
    }

    // ── string/text: skip own NotBlank when the entity already validates it ────

    public function testNonNullableStringFieldGetsNotBlankConstraintWhenPropertyHasNoOwnConstraint(): void
    {
        $metadata = $this->makeMetadata(['name' => ['type' => 'string', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'name');

        $this->assertNotNull($config);
        $this->assertArrayHasKey('constraints', $config['options']);
        $this->assertCount(1, $config['options']['constraints']);
        $this->assertInstanceOf(NotBlank::class, $config['options']['constraints'][0]);
    }

    public function testNullableStringFieldHasNoConstraints(): void
    {
        $metadata = $this->makeMetadata(['name' => ['type' => 'string', 'nullable' => true]]);
        $config = $this->mapper->getFieldConfig($metadata, 'name');

        $this->assertNotNull($config);
        $this->assertArrayNotHasKey('constraints', $config['options']);
    }

    /**
     * The entity's own #[Assert\NotBlank] (normal, recommended practice,
     * independent of this bundle) must not be duplicated by a second,
     * differently-worded constraint from this mapper — that previously
     * produced two separate error messages ("This value should not be
     * blank." and "Name is required.") for the same blank field.
     */
    public function testNonNullableStringFieldSkipsOwnNotBlankWhenPropertyAlreadyHasAValidatorConstraint(): void
    {
        $metadata = $this->makeMetadata(
            ['name' => ['type' => 'string', 'nullable' => false]],
            reflectionClass: EntityWithOwnNotBlankFixture::class,
        );
        $config = $this->mapper->getFieldConfig($metadata, 'name');

        $this->assertNotNull($config);
        $this->assertArrayNotHasKey(
            'constraints',
            $config['options'],
            'Mapper must not add its own NotBlank when the property already declares a validator constraint.'
        );
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

    public function testIntegerNonNullableHasEmptyStringEmptyDataAndValueGuard(): void
    {
        $metadata = $this->makeMetadata(['qty' => ['type' => 'integer', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'qty');

        $this->assertNotNull($config);
        $this->assertSame('', $config['options']['empty_data']);
        $this->assertTrue($config['requiresValueGuard'] ?? false);
        $this->assertArrayNotHasKey('constraints', $config['options']);
        $this->assertSame('Qty is required.', $config['options']['invalid_message']);
    }

    /**
     * Nullable guarded fields also use '' for empty_data (not null) — a
     * literal null is unsafe for date/enum transformers even when the field
     * is nullable, so the mapper does not special-case nullable vs required
     * for these types the way it does for string/text. No guard is
     * attached here (requiresValueGuard is false), since a nullable field
     * has nothing to guard against.
     */
    public function testIntegerNullableHasEmptyStringEmptyDataAndNoValueGuard(): void
    {
        $metadata = $this->makeMetadata(['qty' => ['type' => 'integer', 'nullable' => true]]);
        $config = $this->mapper->getFieldConfig($metadata, 'qty');

        $this->assertNotNull($config);
        $this->assertSame('', $config['options']['empty_data']);
        $this->assertFalse($config['requiresValueGuard'] ?? false);
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

    public function testDecimalNonNullableHasEmptyStringEmptyDataAndValueGuard(): void
    {
        $metadata = $this->makeMetadata(['price' => ['type' => 'decimal', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'price');

        $this->assertNotNull($config);
        $this->assertSame('', $config['options']['empty_data']);
        $this->assertTrue($config['requiresValueGuard'] ?? false);
    }

    public function testDecimalNullableHasEmptyStringEmptyDataAndNoValueGuard(): void
    {
        $metadata = $this->makeMetadata(['price' => ['type' => 'decimal', 'nullable' => true]]);
        $config = $this->mapper->getFieldConfig($metadata, 'price');

        $this->assertNotNull($config);
        $this->assertSame('', $config['options']['empty_data']);
        $this->assertFalse($config['requiresValueGuard'] ?? false);
    }

    public function testFloatNonNullableHasEmptyStringEmptyDataAndValueGuard(): void
    {
        $metadata = $this->makeMetadata(['score' => ['type' => 'float', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'score');

        $this->assertNotNull($config);
        $this->assertSame('', $config['options']['empty_data']);
        $this->assertTrue($config['requiresValueGuard'] ?? false);
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

    public function testBooleanFieldNeverGetsConstraints(): void
    {
        $metadata = $this->makeMetadata(['active' => ['type' => 'boolean', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'active');

        $this->assertNotNull($config);
        $this->assertArrayNotHasKey('constraints', $config['options']);
    }

    // ── Date ──────────────────────────────────────────────────────────────────

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

    public function testDateMutableUsesDatetimeInput(): void
    {
        $metadata = $this->makeMetadata(['dob' => ['type' => 'date', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'dob');

        $this->assertNotNull($config);
        $this->assertSame('datetime', $config['options']['input']);
    }

    public function testDateImmutableUsesDatetimeImmutableInput(): void
    {
        $metadata = $this->makeMetadata(['dob' => ['type' => 'date_immutable', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'dob');

        $this->assertNotNull($config);
        $this->assertSame('datetime_immutable', $config['options']['input']);
    }

    /**
     * This is the exact regression this test guards: a literal null (the
     * pre-fix empty_data value) fails
     * DateTimeToHtml5LocalDateTimeTransformer's is_string() guard outright
     * and throws "Please enter a valid date and time." — on a required
     * field with nothing wrong with it. '' reaches that transformer's
     * documented "return null for empty input" branch instead.
     */
    public function testDateNonNullableHasEmptyStringEmptyDataAndValueGuard(): void
    {
        $metadata = $this->makeMetadata(['dob' => ['type' => 'date', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'dob');

        $this->assertNotNull($config);
        $this->assertSame(
            '',
            $config['options']['empty_data'],
            'A blank required date field must use \'\' as empty_data, not a literal null — '
            . 'DateTimeToHtml5LocalDateTimeTransformer rejects null outright before it ever '
            . 'reaches its own null-handling branch.'
        );
        $this->assertTrue($config['requiresValueGuard'] ?? false);
        $this->assertArrayNotHasKey('constraints', $config['options']);
        $this->assertSame('Dob is required.', $config['options']['invalid_message']);
    }

    /**
     * The same is_string() guard applies regardless of nullability — a
     * nullable date field must also never receive a literal null as
     * empty_data, or it shows the same spurious "Please enter a valid date
     * and time." error despite blank being entirely valid input for it.
     */
    public function testDateNullableHasEmptyStringEmptyDataAndNoValueGuard(): void
    {
        $metadata = $this->makeMetadata(['dob' => ['type' => 'date', 'nullable' => true]]);
        $config = $this->mapper->getFieldConfig($metadata, 'dob');

        $this->assertNotNull($config);
        $this->assertSame('', $config['options']['empty_data']);
        $this->assertFalse($config['requiresValueGuard'] ?? false);
    }

    // ── DateTime ──────────────────────────────────────────────────────────────

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

    public function testDatetimeMutableUsesDatetimeInput(): void
    {
        $metadata = $this->makeMetadata(['createdAt' => ['type' => 'datetime', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'createdAt');

        $this->assertNotNull($config);
        $this->assertSame('datetime', $config['options']['input']);
    }

    public function testDatetimeImmutableUsesDatetimeImmutableInput(): void
    {
        $metadata = $this->makeMetadata(['createdAt' => ['type' => 'datetime_immutable', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'createdAt');

        $this->assertNotNull($config);
        $this->assertSame('datetime_immutable', $config['options']['input']);
    }

    public function testDatetimetzImmutableUsesDatetimeImmutableInput(): void
    {
        $metadata = $this->makeMetadata(['createdAt' => ['type' => 'datetimetz_immutable', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'createdAt');

        $this->assertNotNull($config);
        $this->assertSame('datetime_immutable', $config['options']['input']);
    }

    public function testDatetimeNonNullableHasEmptyStringEmptyDataAndValueGuard(): void
    {
        $metadata = $this->makeMetadata(['createdAt' => ['type' => 'datetime', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'createdAt');

        $this->assertNotNull($config);
        $this->assertSame('', $config['options']['empty_data']);
        $this->assertTrue($config['requiresValueGuard'] ?? false);
    }

    public function testDatetimeImmutableNonNullableHasEmptyStringEmptyDataAndValueGuard(): void
    {
        $metadata = $this->makeMetadata(['createdAt' => ['type' => 'datetime_immutable', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'createdAt');

        $this->assertNotNull($config);
        $this->assertSame('', $config['options']['empty_data']);
        $this->assertTrue($config['requiresValueGuard'] ?? false);
    }

    public function testDatetimeNullableHasEmptyStringEmptyDataAndNoValueGuard(): void
    {
        $metadata = $this->makeMetadata(['createdAt' => ['type' => 'datetime', 'nullable' => true]]);
        $config = $this->mapper->getFieldConfig($metadata, 'createdAt');

        $this->assertNotNull($config);
        $this->assertSame('', $config['options']['empty_data']);
        $this->assertFalse($config['requiresValueGuard'] ?? false);
    }

    public function testDatetimeImmutableNullableHasEmptyStringEmptyDataAndNoValueGuard(): void
    {
        $metadata = $this->makeMetadata(['deletedAt' => ['type' => 'datetime_immutable', 'nullable' => true]]);
        $config = $this->mapper->getFieldConfig($metadata, 'deletedAt');

        $this->assertNotNull($config);
        $this->assertSame(
            '',
            $config['options']['empty_data'],
            'This is the exact field shape (nullable datetime_immutable) that showed '
            . '"Please enter a valid date and time." on a genuinely optional field before this fix.'
        );
        $this->assertFalse($config['requiresValueGuard'] ?? false);
    }

    // ── Time ──────────────────────────────────────────────────────────────────

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

    public function testTimeMutableUsesDatetimeInput(): void
    {
        $metadata = $this->makeMetadata(['startsAt' => ['type' => 'time', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'startsAt');

        $this->assertNotNull($config);
        $this->assertSame('datetime', $config['options']['input']);
    }

    public function testTimeImmutableUsesDatetimeImmutableInput(): void
    {
        $metadata = $this->makeMetadata(['startsAt' => ['type' => 'time_immutable', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'startsAt');

        $this->assertNotNull($config);
        $this->assertSame('datetime_immutable', $config['options']['input']);
    }

    public function testTimeNonNullableHasEmptyStringEmptyDataAndValueGuard(): void
    {
        $metadata = $this->makeMetadata(['startsAt' => ['type' => 'time', 'nullable' => false]]);
        $config = $this->mapper->getFieldConfig($metadata, 'startsAt');

        $this->assertNotNull($config);
        $this->assertSame('', $config['options']['empty_data']);
        $this->assertTrue($config['requiresValueGuard'] ?? false);
    }

    public function testTimeNullableHasEmptyStringEmptyDataAndNoValueGuard(): void
    {
        $metadata = $this->makeMetadata(['startsAt' => ['type' => 'time', 'nullable' => true]]);
        $config = $this->mapper->getFieldConfig($metadata, 'startsAt');

        $this->assertNotNull($config);
        $this->assertSame('', $config['options']['empty_data']);
        $this->assertFalse($config['requiresValueGuard'] ?? false);
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

    public function testNonNullableEnumHasFalsePlaceholder(): void
    {
        $metadata = $this->makeMetadata(
            ['status' => ['type' => 'string', 'nullable' => false]],
            ['status' => TestStatus::class]
        );
        $config = $this->mapper->getFieldConfig($metadata, 'status');

        $this->assertNotNull($config);
        $this->assertFalse($config['options']['placeholder']);
    }

    public function testNonNullableEnumHasEmptyStringEmptyDataAndValueGuard(): void
    {
        $metadata = $this->makeMetadata(
            ['status' => ['type' => 'string', 'nullable' => false]],
            ['status' => TestStatus::class]
        );
        $config = $this->mapper->getFieldConfig($metadata, 'status');

        $this->assertNotNull($config);
        $this->assertSame('', $config['options']['empty_data']);
        $this->assertTrue($config['requiresValueGuard'] ?? false);
        $this->assertArrayNotHasKey('constraints', $config['options']);
        $this->assertSame('Status is required.', $config['options']['invalid_message']);
    }

    public function testNullableEnumHasEmptyStringEmptyDataAndNoValueGuard(): void
    {
        $metadata = $this->makeMetadata(
            ['status' => ['type' => 'string', 'nullable' => true]],
            ['status' => TestStatus::class]
        );
        $config = $this->mapper->getFieldConfig($metadata, 'status');

        $this->assertNotNull($config);
        $this->assertSame('', $config['options']['empty_data']);
        $this->assertFalse($config['requiresValueGuard'] ?? false);
    }

    // ── Nullability mismatch detection ─────────────────────────────────────────

    /**
     * Doctrine nullable: true (the DB permits NULL) combined with a PHP
     * property type that does not allow null is the one combination
     * DoctrineFormTypeMapper cannot safely resolve: a NULL row would crash
     * on hydration regardless of anything the form layer does. It must fail
     * loudly, at form-build time, naming the field — not silently guess.
     */
    public function testDbNullableWithNonNullablePhpPropertyThrowsMismatchException(): void
    {
        $metadata = $this->makeMetadata(
            ['requiredByPhp' => ['type' => 'string', 'nullable' => true]],
            reflectionClass: MismatchedNullabilityFixture::class,
        );

        $this->expectException(NullabilityMismatchException::class);
        $this->expectExceptionMessageMatches('/requiredByPhp/');

        $this->mapper->getFieldConfig($metadata, 'requiredByPhp');
    }

    /**
     * The reverse combination — Doctrine nullable: false with a PHP-nullable
     * property — is the normal, supported "new entity, not filled in yet"
     * shape and must NOT throw.
     */
    public function testDbNonNullableWithNullablePhpPropertyDoesNotThrow(): void
    {
        $metadata = $this->makeMetadata(['name' => ['type' => 'string', 'nullable' => false]]);

        $config = $this->mapper->getFieldConfig($metadata, 'name');

        $this->assertNotNull($config);
    }

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

    public function testSingleValuedAssociationHasAutocompleteEnabled(): void
    {
        /** @var ClassMetadata<object>&\PHPUnit\Framework\MockObject\MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getAssociationTargetClass')->with('category')->willReturn('App\Entity\Category');
        $metadata->method('hasAssociation')->with('category')->willReturn(true);
        $metadata->method('isSingleValuedAssociation')->with('category')->willReturn(true);

        $config = $this->mapper->getAssociationConfig($metadata, 'category');

        $this->assertNotNull($config);
        $this->assertTrue($config['options']['autocomplete']);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Build a stub ClassMetadata with the given field mapping.
     *
     * `$reflectionClass` defaults to AllNullableScalarFixture, whose every
     * property is PHP-nullable. That means the PHP side never disagrees
     * with whichever `nullable` value an individual test configures on the
     * Doctrine side by default: Doctrine nullable:false + PHP-nullable
     * (the common "new entity" shape) is a supported combination, not a
     * mismatch — only Doctrine nullable:true + PHP-non-nullable is, and no
     * test here exercises that against the default fixture. Tests that need
     * a specific reflection shape (the mismatch case, or the has-own-
     * constraint case) pass a different fixture explicitly.
     *
     * @param array<string, array{type: string, nullable: bool}> $fields
     * @param array<string, class-string<\BackedEnum>>           $enumTypes
     * @return ClassMetadata<object>
     */
    private function makeMetadata(
        array $fields,
        array $enumTypes = [],
        string $reflectionClass = AllNullableScalarFixture::class,
    ): ClassMetadata {
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
                $mapping->nullable = $data['nullable'];
                $mapping->enumType = $enumTypes[$field] ?? null;

                return $mapping;
            });
        $metadata->method('hasField')
            ->willReturnCallback(fn (string $field) => isset($fields[$field]));
        $metadata->method('getName')->willReturn($reflectionClass);
        $metadata->method('getReflectionClass')
            ->willReturn(new \ReflectionClass($reflectionClass));

        return $metadata;
    }
}

// ── Reflection fixtures ─────────────────────────────────────────────────────────

/**
 * Every property is PHP-nullable. Used as the default reflection target for
 * makeMetadata() so that, unless a test says otherwise, the PHP side always
 * agrees with (or is more permissive than) whatever `nullable` value that
 * test configures on the mocked Doctrine mapping — see that method's
 * docblock for why that specifically avoids ever tripping the mismatch
 * exception in tests that aren't about the mismatch exception at all.
 */
class AllNullableScalarFixture
{
    public ?string $name = null;
    public ?string $body = null;
    public ?int $count = null;
    public ?int $qty = null;
    public ?float $price = null;
    public ?float $score = null;
    public ?bool $active = null;
    public ?\DateTimeInterface $dob = null;
    public ?\DateTimeInterface $createdAt = null;
    public ?\DateTimeInterface $deletedAt = null;
    public ?\DateTimeInterface $startsAt = null;
    public ?string $status = null;
}

/**
 * A single non-nullable property, paired in tests with a Doctrine
 * nullable:true mapping — the one (DB nullable, PHP non-nullable)
 * combination DoctrineFormTypeMapper cannot safely build a field for.
 */
class MismatchedNullabilityFixture
{
    public string $requiredByPhp = '';
}

/**
 * $name carries its own #[Assert\NotBlank], the normal way a developer
 * would declare this directly on an entity, independent of this bundle.
 * Used to prove the mapper does not add a second, differently-worded
 * NotBlank on top of it.
 */
class EntityWithOwnNotBlankFixture
{
    #[NotBlank]
    public ?string $name = null;
}
