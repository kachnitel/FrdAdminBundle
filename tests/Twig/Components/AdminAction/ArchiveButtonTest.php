<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Components\AdminAction;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Kachnitel\AdminBundle\Tests\Fixtures\ArchivableEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\SoftDeleteEntity;
use Kachnitel\AdminBundle\Tests\Functional\ComponentTestCase;
use Kachnitel\AdminBundle\Twig\Components\AdminAction\ArchiveButton;

/**
 * @group batch-actions
 * @group archive
 */
final class ArchiveButtonTest extends ComponentTestCase
{
    public function testRendersDisabledButtonWhenNothingSelected(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:Action:Archive',
            data: [
                'selectedIds'      => [],
                'entityClass'      => ArchivableEntity::class,
                'entityShortClass' => 'ArchivableEntity',
            ],
        );

        $rendered = (string) $testComponent->render();

        $this->assertStringContainsString('disabled', $rendered);
        $this->assertStringContainsString('Archive Selected (0)', $rendered);
    }

    public function testRendersEnabledButtonWhenRowsSelected(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:Action:Archive',
            data: [
                'selectedIds'      => [1, 2],
                'entityClass'      => ArchivableEntity::class,
                'entityShortClass' => 'ArchivableEntity',
            ],
        );

        $rendered = (string) $testComponent->render();

        $this->assertStringContainsString('Archive Selected (2)', $rendered);
        $this->assertStringNotContainsString(' disabled', $rendered);
    }

    public function testExecuteSetsBooleanArchivedField(): void
    {
        $container = static::getContainer();
        /** @var ManagerRegistry $doctrine */
        $doctrine = $container->get('doctrine');
        /** @var EntityManagerInterface $em */
        $em = $doctrine->getManager();

        $e1 = new ArchivableEntity();
        $e1->setName('Alpha');
        $e2 = new ArchivableEntity();
        $e2->setName('Beta');
        $em->persist($e1);
        $em->persist($e2);
        $em->flush();

        $this->assertFalse($e1->isArchived());
        $this->assertFalse($e2->isArchived());

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:Action:Archive',
            data: [
                'selectedIds'      => [$e1->getId()],
                'entityClass'      => ArchivableEntity::class,
                'entityShortClass' => 'ArchivableEntity',
            ],
        );

        $testComponent->call('execute');

        $em->clear();

        $refreshed1 = $em->find(ArchivableEntity::class, $e1->getId());
        $refreshed2 = $em->find(ArchivableEntity::class, $e2->getId());

        $this->assertTrue($refreshed1?->isArchived(), 'Archived entity should have archived=true');
        $this->assertFalse($refreshed2?->isArchived(), 'Untouched entity must not be archived');
    }

    public function testExecuteSetsNullableDatetimeField(): void
    {
        $container = static::getContainer();
        /** @var ManagerRegistry $doctrine */
        $doctrine = $container->get('doctrine');
        /** @var EntityManagerInterface $em */
        $em = $doctrine->getManager();

        $entity = new SoftDeleteEntity();
        $entity->setName('Soft-delete me');
        $em->persist($entity);
        $em->flush();

        $this->assertNotInstanceOf(\DateTimeImmutable::class, $entity->getDeletedAt());

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:Action:Archive',
            data: [
                'selectedIds'      => [$entity->getId()],
                'entityClass'      => SoftDeleteEntity::class,
                'entityShortClass' => 'SoftDeleteEntity',
            ],
        );

        $testComponent->call('execute');

        $em->clear();

        $refreshed = $em->find(SoftDeleteEntity::class, $entity->getId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $refreshed?->getDeletedAt());
    }

    public function testExecuteWithEmptyIdsDoesNothing(): void
    {
        $container = static::getContainer();
        /** @var ManagerRegistry $doctrine */
        $doctrine = $container->get('doctrine');
        /** @var EntityManagerInterface $em */
        $em = $doctrine->getManager();

        $entity = new ArchivableEntity();
        $entity->setName('Should stay unarchived');
        $em->persist($entity);
        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:Action:Archive',
            data: [
                'selectedIds'      => [],
                'entityClass'      => ArchivableEntity::class,
                'entityShortClass' => 'ArchivableEntity',
            ],
        );

        $testComponent->call('execute');

        $em->clear();
        $refreshed = $em->find(ArchivableEntity::class, $entity->getId());
        $this->assertFalse($refreshed?->isArchived());
    }

    public function testImplementsBatchActionComponentInterface(): void
    {
        $container = static::getContainer();
        $component = $container->get(ArchiveButton::class);

        $this->assertInstanceOf(
            \Kachnitel\AdminBundle\BatchAction\BatchActionComponentInterface::class,
            $component
        );
    }
}
