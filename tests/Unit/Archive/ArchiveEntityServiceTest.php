<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Archive;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Archive\ArchiveConfig;
use Kachnitel\AdminBundle\Archive\ArchiveEntityService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @group archive
 * @covers \Kachnitel\AdminBundle\Archive\ArchiveEntityService
 */
class ArchiveEntityServiceTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    private PropertyAccessorInterface $propertyAccessor;
    private ArchiveEntityService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->service = new ArchiveEntityService($this->em, $this->propertyAccessor);
    }

    // ── archive() ────────────────────────────────────────────────────────────

    /** @test */
    public function archiveSetsBooleanFieldToTrue(): void
    {
        $entity = new class {
            public bool $archived = false;
            public function setArchived(bool $v): void { $this->archived = $v; }
        };

        $config = new ArchiveConfig('item.archived', 'archived', 'boolean', null);

        $this->em->expects($this->once())->method('flush');
        $this->service->archive($entity, $config);

        $this->assertTrue($entity->archived);
    }

    /** @test */
    public function archiveSetsDatetimeFieldToCurrentTime(): void
    {
        $entity = new class {
            public ?\DateTime $deletedAt = null;
            public function setDeletedAt(?\DateTime $v): void { $this->deletedAt = $v; }
        };

        $config = new ArchiveConfig('item.deletedAt', 'deletedAt', 'datetime', null);

        $before = new \DateTime();
        $this->em->expects($this->once())->method('flush');
        $this->service->archive($entity, $config);
        $after = new \DateTime();

        $this->assertNotNull($entity->deletedAt);
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $entity->deletedAt->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $entity->deletedAt->getTimestamp());
    }

    /** @test */
    public function archiveSetsDatetimeImmutableFieldToCurrentTime(): void
    {
        $entity = new class {
            public ?\DateTimeImmutable $archivedAt = null;
            public function setArchivedAt(?\DateTimeImmutable $v): void { $this->archivedAt = $v; }
        };

        $config = new ArchiveConfig('item.archivedAt', 'archivedAt', 'datetime_immutable', null);

        $this->em->expects($this->once())->method('flush');
        $this->service->archive($entity, $config);

        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->archivedAt);
    }

    /** @test */
    public function archiveSetsDatetzImmutableFieldToCurrentTime(): void
    {
        $entity = new class {
            public ?\DateTimeImmutable $deletedAt = null;
            public function setDeletedAt(?\DateTimeImmutable $v): void { $this->deletedAt = $v; }
        };

        $config = new ArchiveConfig('item.deletedAt', 'deletedAt', 'datetimetz_immutable', null);

        $this->em->expects($this->once())->method('flush');
        $this->service->archive($entity, $config);

        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->deletedAt);
    }

    /** @test */
    public function archiveSetsDateFieldToToday(): void
    {
        $entity = new class {
            public ?\DateTime $archivedOn = null;
            public function setArchivedOn(?\DateTime $v): void { $this->archivedOn = $v; }
        };

        $config = new ArchiveConfig('item.archivedOn', 'archivedOn', 'date', null);

        $this->em->expects($this->once())->method('flush');
        $this->service->archive($entity, $config);

        $this->assertNotNull($entity->archivedOn);
        $this->assertSame(date('Y-m-d'), $entity->archivedOn->format('Y-m-d'));
    }

    /** @test */
    public function archiveSetsDateImmutableFieldToToday(): void
    {
        $entity = new class {
            public ?\DateTimeImmutable $expiresOn = null;
            public function setExpiresOn(?\DateTimeImmutable $v): void { $this->expiresOn = $v; }
        };

        $config = new ArchiveConfig('item.expiresOn', 'expiresOn', 'date_immutable', null);

        $this->em->expects($this->once())->method('flush');
        $this->service->archive($entity, $config);

        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->expiresOn);
        $this->assertSame(date('Y-m-d'), $entity->expiresOn->format('Y-m-d'));
    }

    // ── unarchive() ───────────────────────────────────────────────────────────

    /** @test */
    public function unarchiveSetsBooleanFieldToFalse(): void
    {
        $entity = new class {
            public bool $archived = true;
            public function setArchived(bool $v): void { $this->archived = $v; }
        };

        $config = new ArchiveConfig('item.archived', 'archived', 'boolean', null);

        $this->em->expects($this->once())->method('flush');
        $this->service->unarchive($entity, $config);

        $this->assertFalse($entity->archived);
    }

    /** @test */
    public function unarchiveSetsDatetimeFieldToNull(): void
    {
        $entity = new class {
            public ?\DateTime $deletedAt;
            public function __construct() { $this->deletedAt = new \DateTime(); }
            public function setDeletedAt(?\DateTime $v): void { $this->deletedAt = $v; }
        };

        $config = new ArchiveConfig('item.deletedAt', 'deletedAt', 'datetime', null);

        $this->em->expects($this->once())->method('flush');
        $this->service->unarchive($entity, $config);

        $this->assertNull($entity->deletedAt);
    }

    /** @test */
    public function unarchiveSetsDatetimeImmutableFieldToNull(): void
    {
        $entity = new class {
            public ?\DateTimeImmutable $archivedAt;
            public function __construct() { $this->archivedAt = new \DateTimeImmutable(); }
            public function setArchivedAt(?\DateTimeImmutable $v): void { $this->archivedAt = $v; }
        };

        $config = new ArchiveConfig('item.archivedAt', 'archivedAt', 'datetime_immutable', null);

        $this->em->expects($this->once())->method('flush');
        $this->service->unarchive($entity, $config);

        $this->assertNull($entity->archivedAt);
    }

    /** @test */
    public function unarchiveSetsDateFieldToNull(): void
    {
        $entity = new class {
            public ?\DateTime $archivedOn;
            public function __construct() { $this->archivedOn = new \DateTime(); }
            public function setArchivedOn(?\DateTime $v): void { $this->archivedOn = $v; }
        };

        $config = new ArchiveConfig('item.archivedOn', 'archivedOn', 'date', null);

        $this->em->expects($this->once())->method('flush');
        $this->service->unarchive($entity, $config);

        $this->assertNull($entity->archivedOn);
    }

    /** @test */
    public function throwsForUnsupportedDoctrineType(): void
    {
        $entity = new class {};
        $config = new ArchiveConfig('item.status', 'status', 'string', null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unsupported archive field type: string/');

        $this->service->archive($entity, $config);
    }
}
