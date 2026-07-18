<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Archive;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
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
#[UsesClass(Admin::class)]
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class ArchiveServiceExtractFieldTest extends TestCase
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

    #[Test]
    public function extractFieldParsesEntityPrefixBoolean(): void
    {
        $this->assertSame('archived', $this->makeService()->extractField('entity.archived'));
    }

    #[Test]
    public function extractFieldParsesEntityPrefixNullableDateTime(): void
    {
        $this->assertSame('deletedAt', $this->makeService()->extractField('entity.deletedAt'));
    }

    #[Test]
    public function extractFieldParsesEntityPrefixWithUnderscores(): void
    {
        $this->assertSame('deleted_at', $this->makeService()->extractField('entity.deleted_at'));
    }

    #[Test]
    public function extractFieldParsesItemPrefixWithUnderscores(): void
    {
        $this->assertSame('soft_deleted', $this->makeService()->extractField('item.soft_deleted'));
    }

    #[Test]
    public function extractFieldReturnsSameResultForBothPrefixes(): void
    {
        $service = $this->makeService();

        $fromItem   = $service->extractField('item.deletedAt');
        $fromEntity = $service->extractField('entity.deletedAt');

        $this->assertSame($fromItem, $fromEntity);
    }

    // ── extractField: complex expressions that must return null ───────────────
    // @see docs/ARCHIVE.md#Limitations

    #[Test]
    public function extractFieldReturnNullForEntityMethodCall(): void
    {
        // Looks simple but has parens → complex
        $this->assertNull($this->makeService()->extractField('entity.isDeleted()'));
    }

    #[Test]
    public function extractFieldReturnNullForNestedProperty(): void
    {
        $this->assertNull($this->makeService()->extractField('entity.user.deleted'));
    }

    #[Test]
    public function extractFieldReturnNullForUnknownPrefix(): void
    {
        $this->assertNull($this->makeService()->extractField('row.deletedAt'));
    }

    #[Test]
    public function extractFieldReturnNullForBarePropertyName(): void
    {
        $this->assertNull($this->makeService()->extractField('deletedAt'));
    }

    // ── resolveConfig: entity. prefix flows through to DQL condition ──────────

    #[Test]
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

        $this->assertInstanceOf(\Kachnitel\AdminBundle\Archive\ArchiveConfig::class, $config);
        $this->assertSame('entity.archived', $config->expression);
        $this->assertSame('archived', $config->field);
        $this->assertSame('boolean', $config->doctrineType);
    }

    #[Test]
    public function resolveConfigReturnsNullForUnsupportedFieldTypeString(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(archiveExpression: 'item.status'));

        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('string'); // unsupported

        $service = $this->makeService();
        $config  = $service->resolveConfig('App\\Entity\\Product'); // @phpstan-ignore argument.type

        $this->assertNotInstanceOf(\Kachnitel\AdminBundle\Archive\ArchiveConfig::class, $config, 'String field type cannot be DQL-filtered — config must be null.');
    }

    #[Test]
    public function resolveConfigReturnsNullForIntegerFieldType(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(archiveExpression: 'item.deletedFlag'));

        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('integer');

        $config = $this->makeService()->resolveConfig('App\\Entity\\Product'); // @phpstan-ignore argument.type

        $this->assertNotInstanceOf(\Kachnitel\AdminBundle\Archive\ArchiveConfig::class, $config);
    }

    // ── buildDqlCondition: datetimetz types ───────────────────────────────────

    #[Test]
    #[DataProvider('provideDatetimetzTypes')]
    public function buildDqlConditionForDatetimetzTypeInHideMode(string $doctrineType): void
    {
        $condition = $this->makeService()->buildDqlCondition('e', 'deletedAt', $doctrineType, false);

        $this->assertSame('e.deletedAt IS NULL', $condition, "Failed for type: $doctrineType");
    }

    #[Test]
    #[DataProvider('provideDatetimetzTypes')]
    public function buildDqlConditionForDatetimetzTypeInShowAllMode(string $doctrineType): void
    {
        $condition = $this->makeService()->buildDqlCondition('e', 'deletedAt', $doctrineType, true);

        $this->assertNull($condition, "showArchived=true must return null for type: $doctrineType");
    }

    /** @return \Iterator<string, array{string}> */
    public static function provideDatetimetzTypes(): \Iterator
    {
        yield 'datetimetz' => ['datetimetz'];
        yield 'datetimetz_immutable' => ['datetimetz_immutable'];
        yield 'date' => ['date'];
        yield 'date_immutable' => ['date_immutable'];
    }

    // ── buildDqlCondition: alias propagation ──────────────────────────────────

    #[Test]
    public function buildDqlConditionUsesProvidedAlias(): void
    {
        $condition = $this->makeService()->buildDqlCondition('p', 'archived', 'boolean', false);

        $this->assertSame('(p.archived IS NULL OR p.archived = false)', $condition);
    }

    #[Test]
    public function buildDqlConditionUsesProvidedFieldName(): void
    {
        $condition = $this->makeService()->buildDqlCondition('e', 'softDeleted', 'boolean', false);

        $this->assertSame('(e.softDeleted IS NULL OR e.softDeleted = false)', $condition);
    }
}
