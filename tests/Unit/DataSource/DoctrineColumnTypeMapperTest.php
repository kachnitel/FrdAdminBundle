<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\DataSource\DoctrineColumnTypeMapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Kachnitel\AdminBundle\DataSource\DoctrineColumnTypeMapper
 * @group doctrine-data-source
 */
class DoctrineColumnTypeMapperTest extends TestCase
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

    /**
     * @test
     * @dataProvider provideFieldTypes
     */
    public function itMapsDoctrineFieldTypeToAdminColumnType(string $doctrineType, string $expected): void
    {
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('hasAssociation')->willReturn(false);
        $this->metadata->method('getTypeOfField')->willReturn($doctrineType);

        $this->assertSame($expected, $this->mapper->getColumnType($this->metadata, 'someField'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function provideFieldTypes(): array
    {
        return [
            'integer'                   => ['integer',                   'integer'],
            'smallint'                  => ['smallint',                  'integer'],
            'bigint'                    => ['bigint',                    'integer'],
            'decimal'                   => ['decimal',                   'decimal'],
            'float'                     => ['float',                     'decimal'],
            'boolean'                   => ['boolean',                   'boolean'],
            'date'                      => ['date',                      'date'],
            'date_immutable'            => ['date_immutable',            'date'],
            'datetime'                  => ['datetime',                  'datetime'],
            'datetime_immutable'        => ['datetime_immutable',        'datetime'],
            'datetimetz'                => ['datetimetz',                'datetime'],
            'datetimetz_immutable'      => ['datetimetz_immutable',      'datetime'],
            'time'                      => ['time',                      'time'],
            'time_immutable'            => ['time_immutable',            'time'],
            'text'                      => ['text',                      'text'],
            'json'                      => ['json',                      'json'],
            'json_array'                => ['json_array',                'json'],
            'array'                     => ['array',                     'array'],
            'simple_array'              => ['simple_array',              'array'],
            'string (default)'          => ['string',                    'string'],
            'unknown type falls back'   => ['custom_type_xyz',           'string'],
        ];
    }

    // ── Association types ─────────────────────────────────────────────────────

    /** @test */
    public function itReturnCollectionForCollectionValuedAssociation(): void
    {
        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(true);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(true);

        $this->assertSame('collection', $this->mapper->getColumnType($this->metadata, 'tags'));
    }

    /** @test */
    public function itReturnsRelationForSingleValuedAssociation(): void
    {
        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(true);
        $this->metadata->method('isCollectionValuedAssociation')->willReturn(false);

        $this->assertSame('relation', $this->mapper->getColumnType($this->metadata, 'category'));
    }

    // ── Unknown / custom columns ──────────────────────────────────────────────

    /** @test */
    public function itReturnsStringForColumnWithNoDoctrineMapping(): void
    {
        $this->metadata->method('hasField')->willReturn(false);
        $this->metadata->method('hasAssociation')->willReturn(false);

        $this->assertSame('string', $this->mapper->getColumnType($this->metadata, 'virtualField'));
    }
}
