<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Kachnitel\AdminBundle\Archive\ArchiveConfig;
use Kachnitel\AdminBundle\Archive\ArchiveEntityService;
use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\Controller\GenericAdminController;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Tests\Fixtures\DeletableEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\GenericAdminControllerTestDouble;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Covers only the archive/unarchive slice of GenericAdminController — see
 * GenericAdminControllerFormComponentTest for getFormType()/getFormComponentName()
 * and GenericAdminControllerRoutesTest for index()/new()/delete().
 */
#[CoversClass(GenericAdminController::class)]
#[UsesClass(ArchiveConfig::class)]
#[Group('controller')]
#[Group('archive')]
final class GenericAdminControllerArchiveTest extends TestCase
{
    private const ENTITY_NAMESPACE = 'Kachnitel\\AdminBundle\\Tests\\Fixtures\\';

    private Stub&EntityManagerInterface $em;
    /** @var Stub&EntityRepository<object> */
    private Stub&EntityRepository $repository;
    private Stub&EntityDiscoveryService $entityDiscovery;
    private Stub&ArchiveService $archiveService;
    private Stub&ArchiveEntityService $archiveEntityService;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(EntityRepository::class);
        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->em->method('getRepository')->willReturn($this->repository);

        $this->entityDiscovery = $this->createStub(EntityDiscoveryService::class);
        $this->entityDiscovery->method('getAdminEntityShortNames')->willReturn(['DeletableEntity']);

        $this->archiveService = $this->createStub(ArchiveService::class);
        $this->archiveEntityService = $this->createStub(ArchiveEntityService::class);
    }

    private function makeController(?string $requiredRole = 'ROLE_ADMIN'): GenericAdminControllerTestDouble
    {
        return new GenericAdminControllerTestDouble(
            em: $this->em,
            entityDiscovery: $this->entityDiscovery,
            entityNamespace: self::ENTITY_NAMESPACE,
            formNamespace: 'App\\Form\\',
            formSuffix: 'FormType',
            formRegistry: $this->createStub(FormRegistryInterface::class),
            requiredRole: $requiredRole,
        );
    }

    private function archiveRequest(string $action, int $id, ?string $token, ?string $referer = null): Request
    {
        $request = Request::create('/admin/deletable-entity/' . $id . '/' . $action, 'POST');
        if ($token !== null) {
            $request->request->set('_token', $token);
        }
        if ($referer !== null) {
            $request->headers->set('referer', $referer);
        }

        return $request;
    }

    private function archiveConfig(): ArchiveConfig
    {
        return new ArchiveConfig('item.archived', 'archived', 'boolean', null);
    }

    // ── archive() ────────────────────────────────────────────────────────

    #[Test]
    public function archiveThrowsNotFoundWhenEntityMissing(): void
    {
        $this->repository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('No DeletableEntity found for id 99');

        $this->makeController()->archive(
            $this->archiveRequest('archive', 99, 'irrelevant'),
            'deletable-entity',
            99,
            $this->archiveService,
            $this->archiveEntityService,
        );
    }

    #[Test]
    public function archiveThrowsOnInvalidCsrfToken(): void
    {
        $this->repository->method('find')->willReturn(new DeletableEntity(5));
        $controller = $this->makeController();
        $controller->csrfValid = false;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid CSRF token for archive_5');

        $controller->archive(
            $this->archiveRequest('archive', 5, 'bad-token'),
            'deletable-entity',
            5,
            $this->archiveService,
            $this->archiveEntityService,
        );
    }

    #[Test]
    public function archiveThrowsNotFoundWhenArchiveNotConfigured(): void
    {
        $this->repository->method('find')->willReturn(new DeletableEntity(5));
        $this->archiveService->method('resolveConfig')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Archive is not configured for DeletableEntity');

        $this->makeController()->archive(
            $this->archiveRequest('archive', 5, 'valid-token'),
            'deletable-entity',
            5,
            $this->archiveService,
            $this->archiveEntityService,
        );
    }

    #[Test]
    public function archiveSuccessCallsServiceFlashesAndRedirectsToReferer(): void
    {
        $entity = new DeletableEntity(5);
        $config = $this->archiveConfig();
        $this->repository->method('find')->willReturn($entity);
        $this->archiveService->method('resolveConfig')->willReturn($config);

        $archiveEntityService = $this->createMock(ArchiveEntityService::class);
        $archiveEntityService->expects($this->once())->method('archive')->with($entity, $config);

        $controller = $this->makeController();
        $result = $controller->archive(
            $this->archiveRequest('archive', 5, 'valid-token', referer: '/admin/deletable-entity'),
            'deletable-entity',
            5,
            $this->archiveService,
            $archiveEntityService,
        );

        $this->assertSame([['success', 'DeletableEntity #5 archived.']], $controller->flashes);
        $this->assertSame('/admin/deletable-entity', $controller->redirectedTo);
        $this->assertNull($controller->redirectedRoute);
        $this->assertSame(302, $result->getStatusCode());
    }

    #[Test]
    public function archiveSuccessRedirectsToIndexRouteWhenNoReferer(): void
    {
        $this->repository->method('find')->willReturn(new DeletableEntity(5));
        $this->archiveService->method('resolveConfig')->willReturn($this->archiveConfig());

        $controller = $this->makeController();
        $controller->archive(
            $this->archiveRequest('archive', 5, 'valid-token'), // no referer
            'deletable-entity',
            5,
            $this->archiveService,
            $this->archiveEntityService,
        );

        $this->assertNull($controller->redirectedTo);
        $this->assertSame('app_admin_entity_index', $controller->redirectedRoute);
        $this->assertSame(['entitySlug' => 'deletable-entity'], $controller->redirectedRouteParams);
    }

    #[Test]
    public function archiveDeniesAccessBeforeLookingUpEntityWhenNotGranted(): void
    {
        // Tripwire: if find() is ever called, this throws instead of the
        // expected AccessDeniedException, proving the permission check runs first.
        $this->repository->method('find')->willThrowException(new \LogicException('should not be called'));

        $controller = $this->makeController();
        $controller->denyAccess = true;

        $this->expectException(AccessDeniedException::class);

        $controller->archive(
            $this->archiveRequest('archive', 5, 'valid-token'),
            'deletable-entity',
            5,
            $this->archiveService,
            $this->archiveEntityService,
        );
    }

    // ── unarchive() ──────────────────────────────────────────────────────

    #[Test]
    public function unarchiveThrowsNotFoundWhenEntityMissing(): void
    {
        $this->repository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('No DeletableEntity found for id 8');

        $this->makeController()->unarchive(
            $this->archiveRequest('unarchive', 8, 'irrelevant'),
            'deletable-entity',
            8,
            $this->archiveService,
            $this->archiveEntityService,
        );
    }

    #[Test]
    public function unarchiveThrowsOnInvalidCsrfToken(): void
    {
        $this->repository->method('find')->willReturn(new DeletableEntity(5));
        $controller = $this->makeController();
        $controller->csrfValid = false;

        $this->expectExceptionMessage('Invalid CSRF token for unarchive_5');

        $controller->unarchive(
            $this->archiveRequest('unarchive', 5, 'bad-token'),
            'deletable-entity',
            5,
            $this->archiveService,
            $this->archiveEntityService,
        );
    }

    #[Test]
    public function unarchiveSuccessCallsUnarchiveOnServiceAndFlashesUnarchivedMessage(): void
    {
        $entity = new DeletableEntity(6);
        $config = $this->archiveConfig();
        $this->repository->method('find')->willReturn($entity);
        $this->archiveService->method('resolveConfig')->willReturn($config);

        $archiveEntityService = $this->createMock(ArchiveEntityService::class);
        $archiveEntityService->expects($this->once())->method('unarchive')->with($entity, $config);
        $archiveEntityService->expects($this->never())->method('archive');

        $controller = $this->makeController();
        $controller->unarchive(
            $this->archiveRequest('unarchive', 6, 'valid-token'),
            'deletable-entity',
            6,
            $this->archiveService,
            $archiveEntityService,
        );

        $this->assertSame([['success', 'DeletableEntity #6 unarchived.']], $controller->flashes);
    }

    // ── checkEntityPermission() ──────────────────────────────────────────

    #[Test]
    public function permissionCheckIsSkippedEntirelyWhenRequiredRoleIsNull(): void
    {
        $this->repository->method('find')->willReturn(new DeletableEntity(5));
        $this->archiveService->method('resolveConfig')->willReturn($this->archiveConfig());

        $controller = $this->makeController(requiredRole: null);
        $controller->denyAccess = true; // would deny if the check ever ran

        $controller->archive(
            $this->archiveRequest('archive', 5, 'valid-token'),
            'deletable-entity',
            5,
            $this->archiveService,
            $this->archiveEntityService,
        );

        $this->assertSame([['success', 'DeletableEntity #5 archived.']], $controller->flashes);
    }
}
