<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\DataSource\DoctrineColumnTypeMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineColumnTypeMapper::class)]
#[Group('doctrine-data-source')]
#[Group('data-source')]
#[Group('doctrine')]
#[AllowMockObjectsWithoutExpectations]
final class DoctrineColumnTypeMapperTest extends TestCase
{
    private DoctrineColumnTypeMapper $mapper;

    /** @var ClassMetadata<object>&MockObject */
    private ClassMetadata $metadata;

    protected function setUp(): void
    {
        $this->mapper = new DoctrineColumnTypeMapper();
        $this->metadata = $this->createMock(ClassMetadata::class);
    }

    // ── Regular Doctrine field types ──────────────────────────────────────────

    #[Test]
    #[DataProvider('provideFieldTypes')]
    public function itMapsDoctrineFieldTypeToAdminColumnType(string $doctrineType, string $expected): void
    {
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getTypeOfField')->willReturn($doctrineType);

        $this->assertSame($expected, $this->mapper->getColumnType($this->metadata, 'someField'));
    }

    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function provideFieldTypes(): \Iterator
    {
        yield 'integer' => ['integer',                   'integer'];
        yield 'smallint' => ['smallint',                  'integer'];
        yield 'bigint' => ['bigint',                    'integer'];
        yield 'decimal' => ['decimal',                   'decimal'];
        yield 'float' => ['float',                     'decimal'];
        yield 'boolean' => ['boolean',                   'boolean'];
        yield 'date' => ['date',                      'date'];
        yield 'date_immutable' => ['date_immutable',            'date'];
        yield 'datetime' => ['datetime',                  'datetime'];
        yield 'datetime_immutable' => ['datetime_immutable',        'datetime'];
        yield 'datetimetz' => ['datetimetz',                'datetime'];
        yield 'datetimetz_immutable' => ['datetimetz_immutable',      'datetime'];
        yield 'time' => ['time',                      'time'];
        yield 'time_immutable' => ['time_immutable',            'time'];
        yield 'text' => ['text',                      'text'];
        yield 'json' => ['json',                      'json'];
        yield 'json_array' => ['json_array',                'json'];
        yield 'array' => ['array',                     'array'];
        yield 'simple_array' => ['simple_array',              'array'];
        yield 'string (default)' => ['string',                    'string'];
        yield 'unknown type falls back' => ['custom_type_xyz',           'string'];
    }

    // ── Association types ─────────────────────────────────────────────────────

    #[Test]
    public function itReturnCollectionForCollectionValuedAssociation(): void
    {
        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(true);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(true);

        $this->assertSame('collection', $this->mapper->getColumnType($this->metadata, 'tags'));
    }

    #[Test]
    public function itReturnsRelationForSingleValuedAssociation(): void
    {
        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(true);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(false);

        $this->assertSame('relation', $this->mapper->getColumnType($this->metadata, 'category'));
    }

    // ── Unknown / custom columns ──────────────────────────────────────────────

    #[Test]
    public function itReturnsStringForColumnWithNoDoctrineMapping(): void
    {
        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(false);

        $this->assertSame('string', $this->mapper->getColumnType($this->metadata, 'virtualField'));
    }
}
