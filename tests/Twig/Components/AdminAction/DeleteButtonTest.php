<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Components\AdminAction;

use Kachnitel\AdminBundle\Tests\Functional\ComponentTestCase;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Twig\Components\AdminAction\DeleteButton;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @group batch-actions
 */
class DeleteButtonTest extends ComponentTestCase
{
    public function testRendersDisabledButtonWhenNothingSelected(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:Action:Delete',
            data: [
                'selectedIds'      => [],
                'entityClass'      => TestEntity::class,
                'entityShortClass' => 'TestEntity',
            ],
        );

        $rendered = (string) $testComponent->render();

        $this->assertStringContainsString('disabled', $rendered);
        $this->assertStringContainsString('Delete Selected (0)', $rendered);
    }

    public function testRendersEnabledButtonWhenRowsSelected(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:Action:Delete',
            data: [
                'selectedIds'      => [1, 2, 3],
                'entityClass'      => TestEntity::class,
                'entityShortClass' => 'TestEntity',
            ],
        );

        $rendered = (string) $testComponent->render();

        $this->assertStringContainsString('Delete Selected (3)', $rendered);
        $this->assertStringNotContainsString(' disabled', $rendered);
    }

    public function testExecuteWithEmptyIdsDoesNothing(): void
    {
        $container = static::getContainer();
        /** @var ManagerRegistry $doctrine */
        $doctrine = $container->get('doctrine');
        /** @var EntityManagerInterface $em */
        $em = $doctrine->getManager();

        $entity = new TestEntity();
        $entity->setName('Test');
        $em->persist($entity);
        $em->flush();
        $initialCount = $em->getRepository(TestEntity::class)->count([]);

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:Action:Delete',
            data: [
                'selectedIds'      => [],
                'entityClass'      => TestEntity::class,
                'entityShortClass' => 'TestEntity',
            ],
        );

        $testComponent->call('execute');

        $em->clear();
        $this->assertSame($initialCount, $em->getRepository(TestEntity::class)->count([]));
    }

    public function testImplementsBatchActionComponentInterface(): void
    {
        $container = static::getContainer();
        $component = $container->get(DeleteButton::class);

        $this->assertInstanceOf(
            \Kachnitel\AdminBundle\BatchAction\BatchActionComponentInterface::class,
            $component
        );
    }
}
