<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Kachnitel\AdminBundle\Controller\GenericAdminController;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Tests\Fixtures\DeletableEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\GenericAdminControllerTestDouble;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Covers index()/new()/delete() and the resolveEntityName() 404 path. See
 * GenericAdminControllerArchiveTest for archive()/unarchive() and
 * GenericAdminControllerFormComponentTest for getFormType()/getFormComponentName().
 */
#[CoversClass(GenericAdminController::class)]
#[Group('controller')]
final class GenericAdminControllerRoutesTest extends TestCase
{
    private const ENTITY_NAMESPACE = 'Kachnitel\\AdminBundle\\Tests\\Fixtures\\';

    private Stub&EntityManagerInterface $em;
    /** @var Stub&EntityRepository<object> */
    private Stub&EntityRepository $repository;
    private Stub&EntityDiscoveryService $entityDiscovery;
    private Stub&FormRegistryInterface $formRegistry;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(EntityRepository::class);
        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->em->method('getRepository')->willReturn($this->repository);

        $this->entityDiscovery = $this->createStub(EntityDiscoveryService::class);
        $this->entityDiscovery->method('getAdminEntityShortNames')->willReturn(['DeletableEntity']);
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(null);

        $this->formRegistry = $this->createStub(FormRegistryInterface::class);
        $this->formRegistry->method('hasType')->willReturn(false);
    }

    private function makeController(): GenericAdminControllerTestDouble
    {
        return new GenericAdminControllerTestDouble(
            em: $this->em,
            entityDiscovery: $this->entityDiscovery,
            entityNamespace: self::ENTITY_NAMESPACE,
            formNamespace: 'App\\Form\\',
            formSuffix: 'FormType',
            formRegistry: $this->formRegistry,
        );
    }

    // ── index() ──────────────────────────────────────────────────────────

    #[Test]
    public function indexRendersEntityListView(): void
    {
        $controller = $this->makeController();
        $controller->index('deletable-entity');

        $this->assertNotNull($controller->lastRender);
        $this->assertSame('@KachnitelAdmin/admin/index_live.html.twig', $controller->lastRender['view']);
        $this->assertSame(self::ENTITY_NAMESPACE . 'DeletableEntity', $controller->lastRender['parameters']['entityClass']);
        $this->assertSame('DeletableEntity', $controller->lastRender['parameters']['entityShortClass']);
    }

    #[Test]
    public function indexThrowsNotFoundForUnsupportedEntitySlug(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Entity "NonexistentEntity" is not supported.');

        $this->makeController()->index('nonexistent-entity');
    }

    // ── new() ────────────────────────────────────────────────────────────

    #[Test]
    public function newRendersNewEntityFormWithBreadcrumbs(): void
    {
        $controller = $this->makeController();
        $controller->new('deletable-entity');

        $this->assertNotNull($controller->lastRender);
        $this->assertSame('@KachnitelAdmin/admin/new.html.twig', $controller->lastRender['view']);
        $this->assertSame('K:Admin:EntityForm', $controller->lastRender['parameters']['formComponentName']);
        $this->assertCount(1, $controller->lastRender['parameters']['breadcrumbs']);
        $this->assertSame('DeletableEntity', $controller->lastRender['parameters']['breadcrumbs'][0]['label']);
    }

    // ── delete() ─────────────────────────────────────────────────────────

    #[Test]
    public function deleteRemovesEntityAndRedirectsOnSuccess(): void
    {
        $entity = new DeletableEntity(7);
        $this->repository->method('find')->willReturn($entity);

        $request = Request::create('/admin/deletable-entity/7', 'POST');
        $request->attributes->set('_route', 'app_admin_entity_delete');
        $request->request->set('_token', 'valid-token');

        $controller = $this->makeController();
        $result = $controller->delete($request, 'deletable-entity', 7);

        $this->assertSame([['success', 'DeletableEntity #7 deleted.']], $controller->flashes);
        $this->assertSame('app_admin_entity_index', $controller->redirectedRoute);
        $this->assertSame(['entitySlug' => 'deletable-entity'], $controller->redirectedRouteParams);
        $this->assertSame(303, $result->getStatusCode()); // Response::HTTP_SEE_OTHER
    }

    #[Test]
    public function deleteThrowsNotFoundWhenEntityMissing(): void
    {
        $this->repository->method('find')->willReturn(null);

        $request = Request::create('/admin/deletable-entity/404', 'POST');
        $request->attributes->set('_route', 'app_admin_entity_delete');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('No DeletableEntity found for id 404');

        $this->makeController()->delete($request, 'deletable-entity', 404);
    }
}
