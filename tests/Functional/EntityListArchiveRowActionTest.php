<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Tests\Fixtures\ArchivableEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\SoftDeleteEntity;

/**
 * Tests that archive and unarchive row action buttons appear correctly
 * in the EntityList based on the entity's current archive state.
 *
 * @group archive
 */
class EntityListArchiveRowActionTest extends ComponentTestCase
{
    public function testArchiveButtonAppearsForNonArchivedEntity(): void
    {
        $container = static::getContainer();
        /** @var \Doctrine\Persistence\ManagerRegistry $doctrine */
        $doctrine = $container->get('doctrine');
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = $doctrine->getManager();

        $entity = new ArchivableEntity();
        $entity->setName('Active Item');
        // archived = false by default
        $em->persist($entity);
        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => ArchivableEntity::class,
                'entityShortClass' => 'ArchivableEntity',
            ],
        );

        $rendered = (string) $testComponent->render();

        // Archive button should appear for non-archived entity
        $this->assertStringContainsString('🗃', $rendered);
        // Unarchive button should NOT appear
        $this->assertStringNotContainsString('📤', $rendered);
    }

    public function testUnarchiveButtonAppearsForArchivedEntity(): void
    {
        $container = static::getContainer();
        /** @var \Doctrine\Persistence\ManagerRegistry $doctrine */
        $doctrine = $container->get('doctrine');
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = $doctrine->getManager();

        $entity = new ArchivableEntity();
        $entity->setName('Archived Item');
        $entity->setArchived(true);
        $em->persist($entity);
        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => ArchivableEntity::class,
                'entityShortClass' => 'ArchivableEntity',
                'showArchived'     => true,
            ],
        );

        $rendered = (string) $testComponent->render();

        // Unarchive button should appear for archived entity
        $this->assertStringContainsString('📤', $rendered);
        // Archive button should NOT appear
        $this->assertStringNotContainsString('🗃', $rendered);
    }

    public function testMixedArchiveStateShowsCorrectButtonsPerRow(): void
    {
        $container = static::getContainer();
        /** @var \Doctrine\Persistence\ManagerRegistry $doctrine */
        $doctrine = $container->get('doctrine');
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = $doctrine->getManager();

        $active = new ArchivableEntity();
        $active->setName('Active');
        $em->persist($active);

        $archived = new ArchivableEntity();
        $archived->setName('Archived');
        $archived->setArchived(true);
        $em->persist($archived);

        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => ArchivableEntity::class,
                'entityShortClass' => 'ArchivableEntity',
                'showArchived'     => true,
            ],
        );

        $rendered = (string) $testComponent->render();

        // Both icons should appear (one row for each state)
        $this->assertStringContainsString('🗃', $rendered);
        $this->assertStringContainsString('📤', $rendered);
    }

    public function testEntityWithoutArchiveConfigHasNoArchiveButtons(): void
    {
        $container = static::getContainer();
        /** @var \Doctrine\Persistence\ManagerRegistry $doctrine */
        $doctrine = $container->get('doctrine');
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = $doctrine->getManager();

        // TestEntity has no archiveExpression configured
        $entity = new \Kachnitel\AdminBundle\Tests\Fixtures\TestEntity();
        $entity->setName('No archive');
        $em->persist($entity);
        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => \Kachnitel\AdminBundle\Tests\Fixtures\TestEntity::class,
                'entityShortClass' => 'TestEntity',
            ],
        );

        $rendered = (string) $testComponent->render();

        $this->assertStringNotContainsString('🗃', $rendered);
        $this->assertStringNotContainsString('📤', $rendered);
    }

    public function testSoftDeleteEntityShowsArchiveButton(): void
    {
        $container = static::getContainer();
        /** @var \Doctrine\Persistence\ManagerRegistry $doctrine */
        $doctrine = $container->get('doctrine');
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = $doctrine->getManager();

        $entity = new SoftDeleteEntity();
        $entity->setName('Active Soft Delete');
        // deletedAt = null by default → not archived
        $em->persist($entity);
        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => SoftDeleteEntity::class,
                'entityShortClass' => 'SoftDeleteEntity',
            ],
        );

        $rendered = (string) $testComponent->render();

        $this->assertStringContainsString('🗃', $rendered);
        $this->assertStringNotContainsString('📤', $rendered);
    }

    public function testSoftDeleteEntityShowsUnarchiveButtonWhenDeleted(): void
    {
        $container = static::getContainer();
        /** @var \Doctrine\Persistence\ManagerRegistry $doctrine */
        $doctrine = $container->get('doctrine');
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = $doctrine->getManager();

        $entity = new SoftDeleteEntity();
        $entity->setName('Deleted Item');
        $entity->setDeletedAt(new \DateTimeImmutable());
        $em->persist($entity);
        $em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => SoftDeleteEntity::class,
                'entityShortClass' => 'SoftDeleteEntity',
                'showArchived'     => true,
            ],
        );

        $rendered = (string) $testComponent->render();

        $this->assertStringContainsString('📤', $rendered);
        $this->assertStringNotContainsString('🗃', $rendered);
    }
}
