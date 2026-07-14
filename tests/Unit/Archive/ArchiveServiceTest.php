<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Archive;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group archive
 */
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class ArchiveServiceTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var EntityDiscoveryService&MockObject */
    private EntityDiscoveryService $entityDiscovery;

    /** @var ClassMetadata<object>&MockObject */
    private ClassMetadata $metadata;

    private RowActionExpressionLanguage $expressionLanguage;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->entityDiscovery = $this->createMock(EntityDiscoveryService::class);
        $this->metadata = $this->createMock(ClassMetadata::class);
        $this->expressionLanguage = new RowActionExpressionLanguage();

        $this->em->method('getClassMetadata')->willReturn($this->metadata);
    }

    private function makeService(
        ?string $globalExpression = null,
        ?string $globalRole = null,
    ): ArchiveService {
        return new ArchiveService(
            $this->em,
            $this->entityDiscovery,
            $this->expressionLanguage,
            $globalExpression,
            $globalRole,
        );
    }

    // ── resolveConfig() ───────────────────────────────────────────────────────

    #[Test]
    public function resolveConfigReturnsNullWhenNoExpressionConfigured(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(null);

        $config = $this->makeService()->resolveConfig(TestEntity::class);

        $this->assertNotInstanceOf(\Kachnitel\AdminBundle\Archive\ArchiveConfig::class, $config);
    }

    #[Test]
    public function resolveConfigUsesGlobalExpressionWhenNoEntityOverride(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin());

        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('boolean');

        $config = $this->makeService(globalExpression: 'item.archived')->resolveConfig(TestEntity::class);

        $this->assertInstanceOf(\Kachnitel\AdminBundle\Archive\ArchiveConfig::class, $config);
        $this->assertSame('item.archived', $config->expression);
        $this->assertSame('archived', $config->field);
        $this->assertSame('boolean', $config->doctrineType);
        $this->assertNull($config->role);
    }

    #[Test]
    public function resolveConfigUsesEntityExpressionOverGlobal(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(archiveExpression: 'item.deletedAt'));

        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('datetime_immutable');

        $config = $this->makeService(globalExpression: 'item.archived')->resolveConfig(TestEntity::class);

        $this->assertInstanceOf(\Kachnitel\AdminBundle\Archive\ArchiveConfig::class, $config);
        $this->assertSame('item.deletedAt', $config->expression);
        $this->assertSame('deletedAt', $config->field);
    }

    #[Test]
    public function resolveConfigReturnsNullWhenEntityDisablesArchive(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(archiveDisabled: true));

        $config = $this->makeService(globalExpression: 'item.archived')->resolveConfig(TestEntity::class);

        $this->assertNotInstanceOf(\Kachnitel\AdminBundle\Archive\ArchiveConfig::class, $config);
    }

    #[Test]
    public function resolveConfigUsesGlobalRole(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin());
        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('boolean');

        $config = $this->makeService(
            globalExpression: 'item.archived',
            globalRole: 'ROLE_ADMIN',
        )->resolveConfig(TestEntity::class);

        $this->assertInstanceOf(\Kachnitel\AdminBundle\Archive\ArchiveConfig::class, $config);
        $this->assertSame('ROLE_ADMIN', $config->role);
    }

    #[Test]
    public function resolveConfigUsesEntityRoleOverGlobal(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturn(new Admin(archiveRole: 'ROLE_SUPER_ADMIN'));

        $this->metadata->method('hasField')->willReturn(true);
        $this->metadata->method('getTypeOfField')->willReturn('boolean');

        $config = $this->makeService(
            globalExpression: 'item.archived',
            globalRole: 'ROLE_ADMIN',
        )->resolveConfig(TestEntity::class);

        $this->assertInstanceOf(\Kachnitel\AdminBundle\Archive\ArchiveConfig::class, $config);
        $this->assertSame('ROLE_SUPER_ADMIN', $config->role);
    }

    #[Test]
    public function resolveConfigReturnsNullWhenFieldNotInDoctrine(): void
    {
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin());
        $this->metadata->method('hasField')->willReturn(false);

        $config = $this->makeService(globalExpression: 'item.archived')->resolveConfig(TestEntity::class);

        $this->assertNotInstanceOf(\Kachnitel\AdminBundle\Archive\ArchiveConfig::class, $config);
    }

    // ── extractField() ────────────────────────────────────────────────────────

    #[Test]
    public function extractFieldParsesSimpleExpression(): void
    {
        $service = $this->makeService();

        $this->assertSame('archived', $service->extractField('item.archived'));
        $this->assertSame('deletedAt', $service->extractField('item.deletedAt'));
        $this->assertSame('isDeleted', $service->extractField('entity.isDeleted'));
    }

    #[Test]
    public function extractFieldReturnsNullForComplexExpressions(): void
    {
        $service = $this->makeService();

        $this->assertNull($service->extractField('item.archived == true'));
        $this->assertNull($service->extractField('!item.active'));
        $this->assertNull($service->extractField('item.archived != null'));
        $this->assertNull($service->extractField('is_granted("ROLE_ADMIN")'));
    }

    // ── buildDqlCondition() ───────────────────────────────────────────────────

    #[Test]
    public function buildDqlConditionForBooleanFieldHideMode(): void
    {
        $service = $this->makeService();

        $condition = $service->buildDqlCondition('e', 'archived', 'boolean', showArchived: false);

        $this->assertSame('e.archived = false', $condition);
    }

    #[Test]
    public function buildDqlConditionForBooleanFieldShowAllMode(): void
    {
        $service = $this->makeService();

        $condition = $service->buildDqlCondition('e', 'archived', 'boolean', showArchived: true);

        $this->assertNull($condition);
    }

    #[Test]
    public function buildDqlConditionForNullableDatetimeHideMode(): void
    {
        $service = $this->makeService();

        foreach (['datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable', 'date', 'date_immutable'] as $type) {
            $condition = $service->buildDqlCondition('e', 'deletedAt', $type, showArchived: false);
            $this->assertSame('e.deletedAt IS NULL', $condition, "Failed for type: $type");
        }
    }

    #[Test]
    public function buildDqlConditionForNullableDatetimeShowAllMode(): void
    {
        $service = $this->makeService();

        $condition = $service->buildDqlCondition('e', 'deletedAt', 'datetime', showArchived: true);

        $this->assertNull($condition);
    }

    #[Test]
    public function buildDqlConditionReturnsNullForUnsupportedType(): void
    {
        $service = $this->makeService();

        $condition = $service->buildDqlCondition('e', 'status', 'string', showArchived: false);

        $this->assertNull($condition);
    }

    // ── isArchived() ──────────────────────────────────────────────────────────

    #[Test]
    public function isArchivedReturnsTrueForArchivedEntity(): void
    {
        $entity = new class { public bool $archived = true; };
        $service = $this->makeService();

        $this->assertTrue($service->isArchived($entity, 'item.archived'));
    }

    #[Test]
    public function isArchivedReturnsFalseForNonArchivedEntity(): void
    {
        $entity = new class { public bool $archived = false; };
        $service = $this->makeService();

        $this->assertFalse($service->isArchived($entity, 'item.archived'));
    }

    #[Test]
    public function isArchivedReturnsTrueForNonNullDeletedAt(): void
    {
        $entity = new class {
            public ?\DateTimeImmutable $deletedAt;
            public function __construct() { $this->deletedAt = new \DateTimeImmutable(); }
        };
        $service = $this->makeService();

        $this->assertTrue($service->isArchived($entity, 'item.deletedAt != null'));
    }

    #[Test]
    public function isArchivedReturnsFalseOnExpressionError(): void
    {
        $entity = new class {};
        $service = $this->makeService();

        $this->assertFalse($service->isArchived($entity, 'item.nonExistentField'));
    }
}
