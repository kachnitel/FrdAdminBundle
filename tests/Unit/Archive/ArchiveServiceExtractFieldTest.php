<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Archive;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covers edge cases in the ArchiveServiceTest:
 *   - extractField() "entity." prefix variant (regex supports both, only "item." was tested)
 *   - extractField() underscore field names
 *   - extractField() complex expressions that look like simple ones but aren't
 *   - buildDqlCondition() alias 'e' vs custom alias
 *   - resolveConfig() when field type exists but is unsupported (e.g. string)
 *
 * @covers \Kachnitel\AdminBundle\Archive\ArchiveService
 * @group archive
 */
class ArchiveServiceExtractFieldTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var EntityDiscoveryService&MockObject */
    private EntityDiscoveryService $entityDiscovery;

    /** @var ClassMetadata<object>&MockObject */
    private ClassMetadata $metadata;

    protected function setUp(): void
    {
        $this->em             = $this->createMock(EntityManagerInterface::class);
        $this->entityDiscovery = $this->createMock(EntityDiscoveryService::class);
        $this->metadata       = $this->createMock(ClassMetadata::class);

        $this->em->method('getClassMetadata')->willReturn($this->metadata);
    }

    private function makeService(): ArchiveService
    {
        return new ArchiveService(
            $this->em,
            $this->entityDiscovery,
            new RowActionExpressionLanguage(),
            null,
            null,
        );
    }

    // ── extractField: "entity." prefix ────────────────────────────────────────

    /** @test */
    public function extractFieldParsesEntityPrefixBoolean(): void
    {
        $this->assertSame('archived', $this->makeService()->extractField('entity.archived'));
    }

    /** @test */
    public function extractFieldParsesEntityPrefixNullableDateTime(): void
    {
        $this->assertSame('deletedAt', $this->makeService()->extractField('entity.deletedAt'));
    }

    /** @test */
    public function extractFieldParsesEntityPrefixWithUnderscores(): void
    {
        $this->assertSame('deleted_at', $this->makeService()->extractField('entity.deleted_at'));
    }

    /** @test */
    public function extractFieldParsesItemPrefixWithUnderscores(): void
    {
        $this->assertSame('soft_deleted', $this->makeService()->extractField('item.soft_deleted'));
    }

    /** @test */
    public function extractFieldReturnsSameResultForBothPrefixes(): void
    {
        $service = $this->makeService();

        $fromItem   = $service->extractField('item.deletedAt');
        $fromEntity = $service->extractField('entity.deletedAt');

        $this->assertSame($fromItem, $fromEntity);
    }

    // ── extractField: complex expressions that must return null ───────────────
    // @see docs/ARCHIVE.md#Limitations

    /** @test */
    public function extractFieldReturnNullForEntityMethodCall(): void
    {
        // Looks simple but has parens → complex
        $this->assertNull($this->makeService()->extractField('entity.isDeleted()'));
    }

    /** @test */
    public function extractFieldReturnNullForNestedProperty(): void
    {
        $this->assertNull($this->makeService()->extractField('entity.user.deleted'));
    }

    /** @test */
    public function extractFieldReturnNullForUnknownPrefix(): void
    {
        $this->assertNull($this->makeService()->extractField('row.deletedAt'));
    }

    /** @test */
    public function extractFieldReturnNullForBarePropertyName(): void
    {
        $this->assertNull($this->makeService()->extractField('deletedAt'));
    }

    // ── resolveConfig: entity. prefix flows through to DQL condition ──────────

    /** @test */
    public function resolveConfigWorksWithEntityPrefixExpression(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(archiveExpression: 'entity.archived'));

        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('boolean');

        $service = new ArchiveService(
            $this->em,
            $this->entityDiscovery,
            new RowActionExpressionLanguage(),
            null,
            null,
        );

        $config = $service->resolveConfig('App\\Entity\\Product'); // @phpstan-ignore argument.type

        $this->assertNotNull($config);
        $this->assertSame('entity.archived', $config->expression);
        $this->assertSame('archived', $config->field);
        $this->assertSame('boolean', $config->doctrineType);
    }

    /** @test */
    public function resolveConfigReturnsNullForUnsupportedFieldTypeString(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(archiveExpression: 'item.status'));

        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('string'); // unsupported

        $service = $this->makeService();
        $config  = $service->resolveConfig('App\\Entity\\Product'); // @phpstan-ignore argument.type

        $this->assertNull($config, 'String field type cannot be DQL-filtered — config must be null.');
    }

    /** @test */
    public function resolveConfigReturnsNullForIntegerFieldType(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(archiveExpression: 'item.deletedFlag'));

        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('integer');

        $config = $this->makeService()->resolveConfig('App\\Entity\\Product'); // @phpstan-ignore argument.type

        $this->assertNull($config);
    }

    // ── buildDqlCondition: datetimetz types ───────────────────────────────────

    /**
     * @test
     * @dataProvider provideDatetimetzTypes
     */
    public function buildDqlConditionForDatetimetzTypeInHideMode(string $doctrineType): void
    {
        $condition = $this->makeService()->buildDqlCondition('e', 'deletedAt', $doctrineType, false);

        $this->assertSame('e.deletedAt IS NULL', $condition, "Failed for type: $doctrineType");
    }

    /**
     * @test
     * @dataProvider provideDatetimetzTypes
     */
    public function buildDqlConditionForDatetimetzTypeInShowAllMode(string $doctrineType): void
    {
        $condition = $this->makeService()->buildDqlCondition('e', 'deletedAt', $doctrineType, true);

        $this->assertNull($condition, "showArchived=true must return null for type: $doctrineType");
    }

    /** @return array<string, array{0: string}> */
    public static function provideDatetimetzTypes(): array
    {
        return [
            'datetimetz'            => ['datetimetz'],
            'datetimetz_immutable'  => ['datetimetz_immutable'],
            'date'                  => ['date'],
            'date_immutable'        => ['date_immutable'],
        ];
    }

    // ── buildDqlCondition: alias propagation ──────────────────────────────────

    /** @test */
    public function buildDqlConditionUsesProvidedAlias(): void
    {
        $condition = $this->makeService()->buildDqlCondition('p', 'archived', 'boolean', false);

        $this->assertSame('p.archived = false', $condition);
    }

    /** @test */
    public function buildDqlConditionUsesProvidedFieldName(): void
    {
        $condition = $this->makeService()->buildDqlCondition('e', 'softDeleted', 'boolean', false);

        $this->assertSame('e.softDeleted = false', $condition);
    }
}
