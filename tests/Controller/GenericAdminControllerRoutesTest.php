<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Kachnitel\AdminBundle\Controller\GenericAdminController;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Tests\Fixtures\DeletableEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\GenericAdminControllerTestDouble;
use Kachnitel\AdminBundle\Utils\CollectionScopeParameter;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Covers index()/new()/delete() and the resolveEntityName() 404 path. See
 * GenericAdminControllerArchiveTest for archive()/unarchive() and
 * GenericAdminControllerFormComponentTest for getFormType()/getFormComponentName().
 */
#[PHPUnit\CoversClass(GenericAdminController::class)]
#[PHPUnit\UsesClass(CollectionScopeParameter::class)]
#[PHPUnit\UsesClass(Admin::class)]
#[PHPUnit\UsesClass(AdminEntityVoter::class)]
#[PHPUnit\Group('controller')]
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

    private function makeController(?string $requiredRole = 'ROLE_ADMIN'): GenericAdminControllerTestDouble
    {
        return new GenericAdminControllerTestDouble(
            em: $this->em,
            entityDiscovery: $this->entityDiscovery,
            entityNamespace: self::ENTITY_NAMESPACE,
            formNamespace: 'App\\Form\\',
            formSuffix: 'FormType',
            formRegistry: $this->formRegistry,
            requiredRole: $requiredRole,
        );
    }

    // ── index() ──────────────────────────────────────────────────────────

    #[PHPUnit\Test]
    public function indexRendersEntityListView(): void
    {
        $controller = $this->makeController();
        $controller->index(new Request(), 'deletable-entity');

        $this->assertNotNull($controller->lastRender);
        $this->assertSame('@KachnitelAdmin/admin/index_live.html.twig', $controller->lastRender['view']);
        $this->assertSame(self::ENTITY_NAMESPACE . 'DeletableEntity', $controller->lastRender['parameters']['entityClass']);
        $this->assertSame('DeletableEntity', $controller->lastRender['parameters']['entityShortClass']);
    }

    #[PHPUnit\Test]
    public function indexThrowsNotFoundForUnsupportedEntitySlug(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Entity "NonexistentEntity" is not supported.');

        $this->makeController()->index(new Request(), 'nonexistent-entity');
    }

    #[PHPUnit\Test]
    #[PHPUnit\Group('collection-scope')]
    public function indexPassesNoScopeByDefault(): void
    {
        $controller = $this->makeController();
        $controller->index(new Request(), 'deletable-entity');

        $this->assertNotNull($controller->lastRender);
        $this->assertSame([], $controller->lastRender['parameters']['scope']);
    }

    #[PHPUnit\Test]
    #[PHPUnit\Group('collection-scope')]
    public function indexTranslatesCollectionQueryParameterToScope(): void
    {
        $this->entityDiscovery = $this->createStub(EntityDiscoveryService::class);
        $this->entityDiscovery->method('getAdminEntityShortNames')->willReturn(['DeletableEntity']);
        $this->entityDiscovery->method('resolveEntityClass')
            ->willReturnCallback(static fn (string $short): ?string => $short === 'DeletableEntity' ? DeletableEntity::class : null);

        $controller = $this->makeController();
        $controller->index(new Request(['collection' => 'DeletableEntity:7:children']), 'deletable-entity');

        $this->assertNotNull($controller->lastRender);
        $this->assertSame(
            ['ownerClass' => DeletableEntity::class, 'ownerId' => '7', 'property' => 'children'],
            $controller->lastRender['parameters']['scope'],
        );
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidCollectionParameterProvider(): iterable
    {
        yield 'malformed' => ['not-a-scope'];
        yield 'unknown owner' => ['Nope:1:items'];
        yield 'array value' => [['x']];
    }

    #[PHPUnit\Test]
    #[PHPUnit\Group('collection-scope')]
    #[PHPUnit\DataProvider('invalidCollectionParameterProvider')]
    public function indexThrowsNotFoundForInvalidCollectionParameter(mixed $value): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->makeController()->index(new Request(['collection' => $value]), 'deletable-entity');
    }

    // ── new() ────────────────────────────────────────────────────────────

    #[PHPUnit\Test]
    public function newRendersNewEntityFormWithBreadcrumbs(): void
    {
        $controller = $this->makeController();
        $controller->new('deletable-entity');

        $this->assertNotNull($controller->lastRender);
        /** @var array{view: string, parameters: array{formComponentName: string, breadcrumbs: list<array{label: string}>}} $lastRender */
        $lastRender = $controller->lastRender;
        $this->assertSame('@KachnitelAdmin/admin/new.html.twig', $lastRender['view']);
        $this->assertSame('K:Admin:EntityForm', $lastRender['parameters']['formComponentName']);
        $this->assertCount(1, $lastRender['parameters']['breadcrumbs']);
        $this->assertSame('DeletableEntity', $lastRender['parameters']['breadcrumbs'][0]['label']);
    }

    #[PHPUnit\Test]
    #[PHPUnit\Group('action-disabling')]
    public function newThrowsAccessDeniedWhenDisabledAndRequiredRoleIsNull(): void
    {
        $this->entityDiscovery = $this->createStub(EntityDiscoveryService::class);
        $this->entityDiscovery->method('getAdminEntityShortNames')->willReturn(['DeletableEntity']);
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(DeletableEntity::class);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(new Admin(permissions: ['new' => false]));

        $this->expectException(AccessDeniedException::class);

        $this->makeController(requiredRole: null)->new('deletable-entity');
    }

    // ── delete() ─────────────────────────────────────────────────────────

    #[PHPUnit\Test]
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

    #[PHPUnit\Test]
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
