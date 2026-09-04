<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Kachnitel\AdminBundle\Archive\ArchiveConfig;
use Kachnitel\AdminBundle\Archive\ArchiveEntityService;
use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\Controller\GenericAdminController;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Tests\Fixtures\DeletableEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\GenericAdminControllerTestDouble;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Covers the object-level authorization gate added on top of
 * GenericAdminController's existing class-level (string-subject) permission
 * checks: ObjectAuthorizationChecker::denyAccessUnlessGranted() in
 * show()/edit() (via AbstractAdminController's doShow()/doEdit()), delete()
 * (via doDeleteEntity()), and archive()/unarchive().
 *
 * GenericAdminControllerTestDouble installs a PermissiveObjectAuthorizationChecker
 * by default (see its own docblock), so every *other* test suite using it is
 * unaffected by this feature. Each test here installs its own mock via
 * setObjectAuthorizationChecker() to exercise the grant/deny paths.
 *
 * See AdminEntityFormObjectAuthorizationTest / InlineEntityFormObjectAuthorizationTest
 * for the new()-flow coverage: AdminFormSaveTrait::save() / InlineEntityForm::save()
 * run through the LiveComponent kernel rather than this controller-double style,
 * since the entity being authorized doesn't exist until the form binds it.
 */
#[CoversClass(GenericAdminController::class)]
#[UsesClass(ArchiveConfig::class)]
#[Group('controller')]
#[Group('object-authorization')]
final class GenericAdminControllerObjectAuthorizationTest extends TestCase
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

    private function makeController(): GenericAdminControllerTestDouble
    {
        return new GenericAdminControllerTestDouble(
            em: $this->em,
            entityDiscovery: $this->entityDiscovery,
            entityNamespace: self::ENTITY_NAMESPACE,
            formNamespace: 'App\\Form\\',
            formSuffix: 'FormType',
            formRegistry: $this->createStub(FormRegistryInterface::class),
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

    /**
     * A mock expecting exactly one denyAccessUnlessGranted() call with the
     * given attribute and entity, which grants (does nothing).
     */
    private function grantingChecker(string $expectedAttribute, object $expectedEntity): ObjectAuthorizationChecker&MockObject
    {
        $checker = $this->createMock(ObjectAuthorizationChecker::class);
        $checker->expects($this->once())
            ->method('denyAccessUnlessGranted')
            ->with($expectedAttribute, $expectedEntity);

        return $checker;
    }

    /**
     * A mock expecting exactly one denyAccessUnlessGranted() call with the
     * given attribute and entity, which denies by throwing — proving both
     * that the check runs and that it runs with the right arguments.
     */
    private function denyingChecker(string $expectedAttribute, object $expectedEntity): ObjectAuthorizationChecker&MockObject
    {
        $checker = $this->createMock(ObjectAuthorizationChecker::class);
        $checker->expects($this->once())
            ->method('denyAccessUnlessGranted')
            ->with($expectedAttribute, $expectedEntity)
            ->willThrowException(new AccessDeniedException('denied by test double'));

        return $checker;
    }

    // ── show() ───────────────────────────────────────────────────────────

    #[Test]
    public function showChecksObjectAuthorizationWithTheLoadedEntity(): void
    {
        $entity = new DeletableEntity(5);
        $this->repository->method('find')->willReturn($entity);

        $controller = $this->makeController();
        $controller->setObjectAuthorizationChecker($this->grantingChecker(AdminEntityVoter::ADMIN_SHOW, $entity));

        $controller->show('deletable-entity', 5);
    }

    #[Test]
    public function showDeniesWhenObjectAuthorizationDenies(): void
    {
        $entity = new DeletableEntity(5);
        $this->repository->method('find')->willReturn($entity);

        $controller = $this->makeController();
        $controller->setObjectAuthorizationChecker($this->denyingChecker(AdminEntityVoter::ADMIN_SHOW, $entity));

        $this->expectException(AccessDeniedException::class);

        $controller->show('deletable-entity', 5);
    }

    // ── edit() ───────────────────────────────────────────────────────────

    #[Test]
    public function editChecksObjectAuthorizationWithTheLoadedEntity(): void
    {
        $entity = new DeletableEntity(5);
        $this->repository->method('find')->willReturn($entity);

        $controller = $this->makeController();
        $controller->setObjectAuthorizationChecker($this->grantingChecker(AdminEntityVoter::ADMIN_EDIT, $entity));

        $controller->edit('deletable-entity', 5);
    }

    #[Test]
    public function editDeniesWhenObjectAuthorizationDenies(): void
    {
        $entity = new DeletableEntity(5);
        $this->repository->method('find')->willReturn($entity);

        $controller = $this->makeController();
        $controller->setObjectAuthorizationChecker($this->denyingChecker(AdminEntityVoter::ADMIN_EDIT, $entity));

        $this->expectException(AccessDeniedException::class);

        $controller->edit('deletable-entity', 5);
    }

    // ── delete() ─────────────────────────────────────────────────────────

    #[Test]
    public function deleteChecksObjectAuthorizationWithTheLoadedEntity(): void
    {
        $entity = new DeletableEntity(5);
        $this->repository->method('find')->willReturn($entity);

        $controller = $this->makeController();
        $controller->setObjectAuthorizationChecker($this->grantingChecker(AdminEntityVoter::ADMIN_DELETE, $entity));

        $request = Request::create('/admin/deletable-entity/5', 'POST');
        $request->request->set('_token', 'valid-token');

        $controller->delete($request, 'deletable-entity', 5);
    }

    #[Test]
    public function deleteDeniesAndNeverCallsRemoveWhenObjectAuthorizationDenies(): void
    {
        $entity = new DeletableEntity(5);
        $this->repository->method('find')->willReturn($entity);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->repository);
        $em->expects($this->never())->method('remove');
        $em->expects($this->never())->method('flush');

        $controller = new GenericAdminControllerTestDouble(
            em: $em,
            entityDiscovery: $this->entityDiscovery,
            entityNamespace: self::ENTITY_NAMESPACE,
            formNamespace: 'App\\Form\\',
            formSuffix: 'FormType',
            formRegistry: $this->createStub(FormRegistryInterface::class),
        );
        $controller->setObjectAuthorizationChecker($this->denyingChecker(AdminEntityVoter::ADMIN_DELETE, $entity));

        $request = Request::create('/admin/deletable-entity/5', 'POST');
        $request->request->set('_token', 'valid-token');

        $this->expectException(AccessDeniedException::class);

        $controller->delete($request, 'deletable-entity', 5);
    }

    // ── archive() ────────────────────────────────────────────────────────

    #[Test]
    public function archiveChecksObjectAuthorizationWithTheLoadedEntity(): void
    {
        $entity = new DeletableEntity(5);
        $this->repository->method('find')->willReturn($entity);
        $this->archiveService->method('resolveConfig')->willReturn($this->archiveConfig());

        $controller = $this->makeController();
        $controller->setObjectAuthorizationChecker($this->grantingChecker(AdminEntityVoter::ADMIN_ARCHIVE, $entity));

        $controller->archive(
            $this->archiveRequest('archive', 5, 'valid-token'),
            'deletable-entity',
            5,
            $this->archiveService,
            $this->archiveEntityService,
        );
    }

    #[Test]
    public function archiveValidatesCsrfBeforeCheckingObjectAuthorization(): void
    {
        $entity = new DeletableEntity(5);
        $this->repository->method('find')->willReturn($entity);

        $checker = $this->createMock(ObjectAuthorizationChecker::class);
        $checker->expects($this->never())->method('denyAccessUnlessGranted');

        $controller = $this->makeController();
        $controller->csrfValid = false;
        $controller->setObjectAuthorizationChecker($checker);

        // InvalidArgumentException (CSRF), not AccessDeniedException, proves
        // CSRF is validated before — not after — object-level authorization.
        // This closes a permission-oracle leak: under the old ordering, a
        // request with a bad CSRF token still revealed (via 403 vs 400)
        // whether the current user had object-level access to this specific
        // row. The never() expectation above additionally proves the auth
        // checker isn't just denied-and-ignored — it's never consulted at all.
        $this->expectException(\InvalidArgumentException::class);

        $controller->archive(
            $this->archiveRequest('archive', 5, 'irrelevant'),
            'deletable-entity',
            5,
            $this->archiveService,
            $this->archiveEntityService,
        );
    }

    #[Test]
    public function deleteValidatesCsrfBeforeCheckingObjectAuthorization(): void
    {
        $entity = new DeletableEntity(5);
        $this->repository->method('find')->willReturn($entity);

        $checker = $this->createMock(ObjectAuthorizationChecker::class);
        $checker->expects($this->never())->method('denyAccessUnlessGranted');

        $controller = $this->makeController();
        $controller->csrfValid = false;
        $controller->setObjectAuthorizationChecker($checker);

        $this->expectException(\InvalidArgumentException::class);

        $request = Request::create('/admin/deletable-entity/5', 'POST');
        $request->request->set('_token', 'irrelevant');

        $controller->delete($request, 'deletable-entity', 5);
    }

    #[Test]
    public function archiveDeniedNeverCallsArchiveEntityService(): void
    {
        $entity = new DeletableEntity(5);
        $this->repository->method('find')->willReturn($entity);

        $archiveEntityService = $this->createMock(ArchiveEntityService::class);
        $archiveEntityService->expects($this->never())->method('archive');

        $controller = $this->makeController();
        $controller->setObjectAuthorizationChecker($this->denyingChecker(AdminEntityVoter::ADMIN_ARCHIVE, $entity));

        $this->expectException(AccessDeniedException::class);

        $controller->archive(
            $this->archiveRequest('archive', 5, 'valid-token'),
            'deletable-entity',
            5,
            $this->archiveService,
            $archiveEntityService,
        );
    }

    // ── unarchive() ──────────────────────────────────────────────────────

    #[Test]
    public function unarchiveChecksObjectAuthorizationWithTheLoadedEntity(): void
    {
        $entity = new DeletableEntity(6);
        $this->repository->method('find')->willReturn($entity);
        $this->archiveService->method('resolveConfig')->willReturn($this->archiveConfig());

        $controller = $this->makeController();
        $controller->setObjectAuthorizationChecker($this->grantingChecker(AdminEntityVoter::ADMIN_ARCHIVE, $entity));

        $controller->unarchive(
            $this->archiveRequest('unarchive', 6, 'valid-token'),
            'deletable-entity',
            6,
            $this->archiveService,
            $this->archiveEntityService,
        );
    }

    #[Test]
    public function unarchiveDeniesWhenObjectAuthorizationDenies(): void
    {
        $entity = new DeletableEntity(6);
        $this->repository->method('find')->willReturn($entity);

        $controller = $this->makeController();
        $controller->setObjectAuthorizationChecker($this->denyingChecker(AdminEntityVoter::ADMIN_ARCHIVE, $entity));

        $this->expectException(AccessDeniedException::class);

        $controller->unarchive(
            $this->archiveRequest('unarchive', 6, 'valid-token'),
            'deletable-entity',
            6,
            $this->archiveService,
            $this->archiveEntityService,
        );
    }

    // ── Regression: entities without the flag are unaffected ─────────────

    #[Test]
    public function defaultPermissiveCheckerNeverDeniesWhenControllerConstructedDirectly(): void
    {
        // No setObjectAuthorizationChecker() call at all — proves the
        // PermissiveObjectAuthorizationChecker GenericAdminControllerTestDouble
        // installs by default is enough for every pre-existing test in
        // GenericAdminControllerArchiveTest / GenericAdminControllerRoutesTest /
        // GenericAdminControllerFormComponentTest to keep working unchanged.
        $entity = new DeletableEntity(5);
        $this->repository->method('find')->willReturn($entity);
        $this->archiveService->method('resolveConfig')->willReturn($this->archiveConfig());

        $controller = $this->makeController();

        $controller->show('deletable-entity', 5);
        $controller->edit('deletable-entity', 5);
        $controller->archive(
            $this->archiveRequest('archive', 5, 'valid-token'),
            'deletable-entity',
            5,
            $this->archiveService,
            $this->archiveEntityService,
        );

        $this->addToAssertionCount(1); // success criterion: none of the above threw
    }
}
