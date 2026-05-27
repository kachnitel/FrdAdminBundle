<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
use Kachnitel\AdminBundle\Service\DoctrineValueCoercer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Kachnitel\AdminBundle\Service\DoctrineValueCoercer
 * @group auto-form
 */
class DoctrineValueCoercerTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    private DoctrineValueCoercer $coercer;

    protected function setUp(): void
    {
        $this->em      = $this->createMock(EntityManagerInterface::class);
        $this->coercer = new DoctrineValueCoercer($this->em);
    }

    /**
     * Build a minimal ClassMetadata stub with one field.
     *
     * @param ClassMetadata<object>&MockObject $metadata
     * @param class-string<\BackedEnum>|null $enumType
     */
    private function stubField(
        MockObject $metadata,
        string $property,
        string $doctrineType,
        bool $nullable = false,
        ?string $enumType = null,
    ): void {
        $mapping           = new FieldMapping(type: $doctrineType, fieldName: $property, columnName: $property);
        $mapping->nullable = $nullable;

        if ($enumType !== null) {
            $mapping->enumType = $enumType;
        }

        $metadata->method('hasField')->with($property)->willReturn(true);
        $metadata->method('hasAssociation')->with($property)->willReturn(false);
        $metadata->method('getFieldMapping')->with($property)->willReturn($mapping);
    }

    /** @return ClassMetadata<object>&MockObject */
    private function makeMetadata(): MockObject
    {
        return $this->createMock(ClassMetadata::class);
    }

    // ── Scalar types ───────────────────────────────────────────────────────────

    #[DataProvider('integerTypesProvider')]
    public function testCoercesToInt(string $doctrineType): void
    {
        $metadata = $this->makeMetadata();
        $this->stubField($metadata, 'count', $doctrineType);

        $this->assertSame(42, $this->coercer->coerce($metadata, 'count', '42'));
    }

    /** @return array<string, array{string}> */
    public static function integerTypesProvider(): array
    {
        return [
            'integer'  => ['integer'],
            'smallint' => ['smallint'],
            'bigint'   => ['bigint'],
        ];
    }

    #[DataProvider('floatTypesProvider')]
    public function testCoercesToFloat(string $doctrineType): void
    {
        $metadata = $this->makeMetadata();
        $this->stubField($metadata, 'price', $doctrineType);

        $this->assertSame(3.14, $this->coercer->coerce($metadata, 'price', '3.14'));
    }

    /** @return array<string, array{string}> */
    public static function floatTypesProvider(): array
    {
        return [
            'decimal' => ['decimal'],
            'float'   => ['float'],
        ];
    }

    public function testCoercesToStringForStringType(): void
    {
        $metadata = $this->makeMetadata();
        $this->stubField($metadata, 'name', 'string');

        $this->assertSame('hello', $this->coercer->coerce($metadata, 'name', 'hello'));
    }

    // ── Boolean ────────────────────────────────────────────────────────────────

    #[DataProvider('boolTruthyProvider')]
    public function testCoercesToBoolTrue(mixed $raw): void
    {
        $metadata = $this->makeMetadata();
        $this->stubField($metadata, 'active', 'boolean');

        $this->assertTrue($this->coercer->coerce($metadata, 'active', $raw));
    }

    /** @return array<string, array{mixed}> */
    public static function boolTruthyProvider(): array
    {
        return [
            '"1"'  => ['1'],
            '"on"' => ['on'],
            'true' => [true],
        ];
    }

    #[DataProvider('boolFalsyProvider')]
    public function testCoercesToBoolFalse(mixed $raw): void
    {
        $metadata = $this->makeMetadata();
        $this->stubField($metadata, 'active', 'boolean');

        $this->assertFalse($this->coercer->coerce($metadata, 'active', $raw));
    }

    /** @return array<string, array{mixed}> */
    public static function boolFalsyProvider(): array
    {
        return [
            '"0"'   => ['0'],
            '"off"' => ['off'],
            'false' => [false],
        ];
    }

    // ── Date / Datetime / Time ─────────────────────────────────────────────────

    public function testCoercesDate(): void
    {
        $metadata = $this->makeMetadata();
        $this->stubField($metadata, 'birthDate', 'date');

        $result = $this->coercer->coerce($metadata, 'birthDate', '1990-06-15');

        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertSame('1990-06-15', $result->format('Y-m-d'));
    }

    public function testCoercesDateImmutable(): void
    {
        $metadata = $this->makeMetadata();
        $this->stubField($metadata, 'birthDate', 'date_immutable');

        $result = $this->coercer->coerce($metadata, 'birthDate', '2000-01-01');

        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    #[DataProvider('datetimeTypesProvider')]
    public function testCoercesDatetime(string $doctrineType): void
    {
        $metadata = $this->makeMetadata();
        $this->stubField($metadata, 'createdAt', $doctrineType);

        $result = $this->coercer->coerce($metadata, 'createdAt', '2024-03-15T14:30');

        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertSame('2024-03-15', $result->format('Y-m-d'));
        $this->assertSame('14:30', $result->format('H:i'));
    }

    /** @return array<string, array{string}> */
    public static function datetimeTypesProvider(): array
    {
        return [
            'datetime'             => ['datetime'],
            'datetime_immutable'   => ['datetime_immutable'],
            'datetimetz'           => ['datetimetz'],
            'datetimetz_immutable' => ['datetimetz_immutable'],
        ];
    }

    public function testCoercesTime(): void
    {
        $metadata = $this->makeMetadata();
        $this->stubField($metadata, 'meetingTime', 'time');

        $result = $this->coercer->coerce($metadata, 'meetingTime', '09:30');

        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
        $this->assertSame('09:30', $result->format('H:i'));
    }

    // ── Nullable ───────────────────────────────────────────────────────────────

    public function testNullableFieldReturnsNullForEmptyString(): void
    {
        $metadata = $this->makeMetadata();
        $this->stubField($metadata, 'note', 'string', nullable: true);

        $this->assertNull($this->coercer->coerce($metadata, 'note', ''));
    }

    public function testNonNullableFieldReturnsEmptyStringUnchanged(): void
    {
        $metadata = $this->makeMetadata();
        $this->stubField($metadata, 'name', 'string', nullable: false);

        $this->assertSame('', $this->coercer->coerce($metadata, 'name', ''));
    }

    public function testNullRawValueOnNullableReturnsNull(): void
    {
        $metadata = $this->makeMetadata();
        $this->stubField($metadata, 'note', 'string', nullable: true);

        $this->assertNull($this->coercer->coerce($metadata, 'note', null));
    }

    // ── Enum ───────────────────────────────────────────────────────────────────

    public function testCoercesBackedEnum(): void
    {
        $metadata = $this->makeMetadata();
        $this->stubField($metadata, 'status', 'string', enumType: CoercerTestStatus::class);

        $result = $this->coercer->coerce($metadata, 'status', 'active');

        $this->assertSame(CoercerTestStatus::ACTIVE, $result);
    }

    // ── Association ────────────────────────────────────────────────────────────

    public function testCoercesAssociationToReference(): void
    {
        $proxy = new \stdClass();

        $metadata = $this->makeMetadata();
        $metadata->method('hasAssociation')->with('category')->willReturn(true);
        $metadata->method('hasField')->with('category')->willReturn(false);
        $metadata->method('getAssociationTargetClass')
            ->with('category')
            ->willReturn(\stdClass::class);

        $this->em->method('getReference')
            ->with(\stdClass::class, 5)
            ->willReturn($proxy);

        $result = $this->coercer->coerce($metadata, 'category', '5');

        $this->assertSame($proxy, $result);
    }

    public function testNullableAssociationReturnsNullForEmptyString(): void
    {
        $metadata = $this->makeMetadata();
        $metadata->method('hasAssociation')->with('category')->willReturn(true);
        $metadata->method('hasField')->with('category')->willReturn(false);

        $this->em->expects($this->never())->method('getReference');

        $this->assertNull($this->coercer->coerce($metadata, 'category', ''));
    }

    // ── coerceAll ──────────────────────────────────────────────────────────────

    public function testCoerceAllCoercesMultipleFields(): void
    {
        $metadata = $this->makeMetadata();

        $nameMapping           = new FieldMapping(type: 'string', fieldName: 'name', columnName: 'name');
        $countMapping          = new FieldMapping(type: 'integer', fieldName: 'count', columnName: 'count');
        $nameMapping->nullable = false;

        $metadata->method('hasField')->willReturnMap([
            ['name', true],
            ['count', true],
        ]);
        $metadata->method('hasAssociation')->willReturn(false);
        $metadata->method('getFieldMapping')->willReturnMap([
            ['name', $nameMapping],
            ['count', $countMapping],
        ]);

        $result = $this->coercer->coerceAll($metadata, ['name' => 'Ducky', 'count' => '7']);

        $this->assertSame('Ducky', $result['name']);
        $this->assertSame(7, $result['count']);
    }
}

enum CoercerTestStatus: string
{
    case ACTIVE   = 'active';
    case INACTIVE = 'inactive';
}
