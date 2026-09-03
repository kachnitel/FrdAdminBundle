<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\ObjectAuthEntity;
use PHPUnit\Framework\Attributes\Group;

#[Group('object-authorization')]
#[Group('row-actions')]
final class EntityListObjectAuthorizationTest extends ComponentTestCase
{
    protected static function getKernelClass(): string
    {
        return ObjectAuthorizationTestKernel::class;
    }

    public function testDeniedObjectDoesNotRenderEditActionInEntityList(): void
    {
        $entity = new ObjectAuthEntity();
        $entity->setName('Forbidden Item');
        $entity->setKind(ObjectAuthEntity::KIND_FORBIDDEN);
        $this->em->persist($entity);
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => ObjectAuthEntity::class,
                'entityShortClass' => 'ObjectAuthEntity',
            ],
        );

        $rendered = (string) $component->render();

        $this->assertActionNotRendered('🖊', $rendered,
            'The real ObjectAuthorizationChecker must hide edit row actions for denied object instances in the EntityList path.',
        );
    }

    public function testAllowedObjectStillRendersEditActionInEntityList(): void
    {
        $entity = new ObjectAuthEntity();
        $entity->setName('Allowed Item');
        $entity->setKind(ObjectAuthEntity::KIND_ALLOWED);
        $this->em->persist($entity);
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass' => ObjectAuthEntity::class,
                'entityShortClass' => 'ObjectAuthEntity',
            ],
        );

        $rendered = (string) $component->render();

        $this->assertActionRendered('🖊', $rendered,
            'The real ObjectAuthorizationChecker must keep edit row actions visible for allowed object instances in the EntityList path.',
        );
    }
}
