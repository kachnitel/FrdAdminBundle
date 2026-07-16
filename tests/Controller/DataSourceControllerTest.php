<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Controller;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Controller\DataSourceController;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\DataSourceContracts\DataSourceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Unit tests for DataSourceController.
 *
 * Rather than reaching for a real Symfony container, this test overrides
 * render()/isGranted()/denyAccessUnlessGranted() on a thin test double so
 * the actual dashboard()/dataSourceIndex()/dataSourceShow() bodies — the
 * sorting, filtering, mapping, and 404 logic — run for real and are
 * asserted against directly.
 *
 * Override signatures (isGranted, denyAccessUnlessGranted, render) were
 * checked against Symfony\Bundle\FrameworkBundle\Controller\AbstractController
 * on 6.4 through 8.2 — unchanged across that range. denyAccessUnlessGranted()
 * as of 7.2+ internally routes through a new getAccessDecision() method
 * rather than calling $this->isGranted() directly, but the double replaces
 * the whole method body instead of delegating to parent::, so that internal
 * change doesn't affect it either way.
 */
#[CoversClass(DataSourceController::class)]
#[UsesClass(Admin::class)]
#[Group('controller')]
#[Group('datasource-controller')]
final class DataSourceControllerTest extends TestCase
{
    /** @var Stub&EntityDiscoveryService */
    private Stub $entityDiscovery;

    /** @var Stub&DataSourceRegistry */
    private Stub $dataSourceRegistry;

    protected function setUp(): void
    {
        $this->entityDiscovery = $this->createStub(EntityDiscoveryService::class);
        $this->dataSourceRegistry = $this->createStub(DataSourceRegistry::class);
    }

    /**
     * @param DataSourceRegistry|null $dataSourceRegistry Override for tests that need
     *   argument-level verification on get() — a Stub can't do ->expects(), so those
     *   tests pass a local ->createMock() here instead of using the shared property.
     */
    private function makeController(
        ?string $requiredRole = 'ROLE_ADMIN',
        ?DataSourceRegistry $dataSourceRegistry = null,
    ): DataSourceControllerTestDouble {
        return new DataSourceControllerTestDouble(
            $this->entityDiscovery,
            $dataSourceRegistry ?? $this->dataSourceRegistry,
            'App\\Entity\\',
            $requiredRole,
        );
    }

    // ── dashboard() ──────────────────────────────────────────────────────────

    #[Test]
    public function dashboardRendersEntitiesSortedByLabelWhenNoRoleRequired(): void
    {
        $this->entityDiscovery->method('getAdminEntityShortNames')
            ->willReturn(['Zebra', 'Apple', 'NoAttribute']);
        $this->entityDiscovery->method('resolveEntityClass')
            ->willReturnCallback(fn (string $name): ?string => match ($name) {
                'Zebra' => DsCtrlFixtureZebra::class,
                'Apple' => DsCtrlFixtureApple::class,
                default => null,
            });
        $this->entityDiscovery->method('getAdminAttribute')
            ->willReturnCallback(fn (string $class): ?Admin => match ($class) {
                DsCtrlFixtureZebra::class => new Admin(label: 'Zebra Label', icon: 'icon-z'),
                DsCtrlFixtureApple::class => new Admin(label: 'Apple Label'),
                default => null,
            });

        $auditLog = $this->createStub(DataSourceInterface::class);
        $auditLog->method('getLabel')->willReturn('Audit Logs');
        $auditLog->method('getIcon')->willReturn('history');

        $doctrineSource = $this->createStub(DoctrineDataSource::class);

        $this->dataSourceRegistry->method('all')->willReturn([
            'products'  => $doctrineSource,
            'audit-log' => $auditLog,
        ]);

        $controller = $this->makeController(requiredRole: null);
        $controller->dashboard();

        $this->assertNotNull($controller->lastRender);
        $this->assertSame('@KachnitelAdmin/admin/dashboard.html.twig', $controller->lastRender['view']);

        /** @var list<array<string, mixed>> $entities */
        $entities = $controller->lastRender['parameters']['entities'];
        // Sorted alphabetically by resolved label: Apple, NoAttribute, Zebra
        $this->assertSame(['Apple Label', 'No Attribute', 'Zebra Label'], array_column($entities, 'label'));
        $this->assertSame(['Apple', 'NoAttribute', 'Zebra'], array_column($entities, 'name'));
        $this->assertSame([null, null, 'icon-z'], array_column($entities, 'icon'));
        $this->assertSame(['apple', 'no-attribute', 'zebra'], array_column($entities, 'slug'));
        $this->assertSame(['entity', 'entity', 'entity'], array_column($entities, 'type'));

        // DoctrineDataSource instances are excluded from the dataSources listing
        /** @var list<array<string, mixed>> $dataSources */
        $dataSources = $controller->lastRender['parameters']['dataSources'];
        $this->assertCount(1, $dataSources);
        $this->assertSame('audit-log', $dataSources[0]['identifier']);
        $this->assertSame('Audit Logs', $dataSources[0]['label']);
        $this->assertSame('history', $dataSources[0]['icon']);
        $this->assertSame('datasource', $dataSources[0]['type']);
    }

    #[Test]
    public function dashboardThrowsWhenGlobalPermissionDeniedAndRoleRequired(): void
    {
        $this->entityDiscovery->method('getAdminEntityShortNames')->willReturn([]);
        $this->dataSourceRegistry->method('all')->willReturn([]);

        $controller = $this->makeController(requiredRole: 'ROLE_ADMIN');
        $controller->denyAccess = true;

        $this->expectException(AccessDeniedException::class);

        $controller->dashboard();
    }

    #[Test]
    public function dashboardFiltersOutEntitiesTheUserCannotAccessWhenRoleRequired(): void
    {
        $this->entityDiscovery->method('getAdminEntityShortNames')
            ->willReturn(['Apple', 'Zebra']);
        $this->entityDiscovery->method('resolveEntityClass')->willReturn(null);
        $this->entityDiscovery->method('getAdminAttribute')->willReturn(null);
        $this->dataSourceRegistry->method('all')->willReturn([]);

        $controller = $this->makeController(requiredRole: 'ROLE_ADMIN');
        $controller->denyAccess = false; // global check passes
        $controller->grantResults = [
            AdminEntityVoter::ADMIN_INDEX . ':Apple' => false,
            AdminEntityVoter::ADMIN_INDEX . ':Zebra' => true,
        ];

        $controller->dashboard();

        $this->assertNotNull($controller->lastRender);
        /** @var list<array<string, mixed>> $entities */
        $entities = $controller->lastRender['parameters']['entities'];
        $this->assertSame(['Zebra'], array_column($entities, 'name'));
    }

    // ── dataSourceIndex() ────────────────────────────────────────────────────

    #[Test]
    public function dataSourceIndexRendersWhenDataSourceFound(): void
    {
        $dataSource = $this->createStub(DataSourceInterface::class);

        // Local mock (not the shared Stub property) so the exact dataSourceId
        // reaching the registry lookup is actually verified, not just assumed.
        $registry = $this->createMock(DataSourceRegistry::class);
        $registry->expects($this->once())->method('get')->with('audit-log')->willReturn($dataSource);

        $controller = $this->makeController(requiredRole: null, dataSourceRegistry: $registry);
        $controller->dataSourceIndex('audit-log');

        $this->assertNotNull($controller->lastRender);
        $this->assertSame('@KachnitelAdmin/admin/datasource_index.html.twig', $controller->lastRender['view']);
        $this->assertSame('audit-log', $controller->lastRender['parameters']['dataSourceId']);
        $this->assertSame($dataSource, $controller->lastRender['parameters']['dataSource']);
    }

    #[Test]
    public function dataSourceIndexThrowsNotFoundWhenDataSourceMissing(): void
    {
        $this->dataSourceRegistry->method('get')->willReturn(null);

        $controller = $this->makeController(requiredRole: null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Data source "missing" not found.');

        $controller->dataSourceIndex('missing');
    }

    #[Test]
    public function dataSourceIndexThrowsAccessDeniedBeforeLookingUpDataSourceWhenRoleRequired(): void
    {
        // Tripwire: if get() is ever called, this is misconfigured to fail loudly.
        $this->dataSourceRegistry->method('get')
            ->willThrowException(new \LogicException('should not be called'));

        $controller = $this->makeController(requiredRole: 'ROLE_ADMIN');
        $controller->denyAccess = true;

        $this->expectException(AccessDeniedException::class);

        $controller->dataSourceIndex('audit-log');
    }

    // ── dataSourceShow() ─────────────────────────────────────────────────────

    #[Test]
    public function dataSourceShowRendersItemWhenFoundAndSupported(): void
    {
        $item = new \stdClass();

        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->expects($this->once())->method('supportsAction')->with('show')->willReturn(true);
        $dataSource->expects($this->once())->method('find')->with('42')->willReturn($item);

        // Local mock — same reasoning as dataSourceIndexRendersWhenDataSourceFound:
        // this has TWO string params (dataSourceId, id), so verifying which one
        // reaches the registry lookup actually matters here.
        $registry = $this->createMock(DataSourceRegistry::class);
        $registry->expects($this->once())->method('get')->with('audit-log')->willReturn($dataSource);

        $controller = $this->makeController(requiredRole: null, dataSourceRegistry: $registry);
        $controller->dataSourceShow('audit-log', '42');

        $this->assertNotNull($controller->lastRender);
        $this->assertSame('@KachnitelAdmin/admin/datasource_show.html.twig', $controller->lastRender['view']);
        $this->assertSame('audit-log', $controller->lastRender['parameters']['dataSourceId']);
        $this->assertSame($dataSource, $controller->lastRender['parameters']['dataSource']);
        $this->assertSame($item, $controller->lastRender['parameters']['item']);
    }

    #[Test]
    public function dataSourceShowThrowsNotFoundWhenDataSourceMissing(): void
    {
        $this->dataSourceRegistry->method('get')->willReturn(null);

        $controller = $this->makeController(requiredRole: null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Data source "missing" not found.');

        $controller->dataSourceShow('missing', '1');
    }

    #[Test]
    public function dataSourceShowThrowsNotFoundWhenActionNotSupported(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->expects($this->once())->method('supportsAction')->with('show')->willReturn(false);
        // Proves the method genuinely short-circuits rather than happening to
        // pass because find() wasn't stubbed (a mock with no method('find')
        // configured would silently return null if called, not fail).
        $dataSource->expects($this->never())->method('find');

        $this->dataSourceRegistry->method('get')->willReturn($dataSource);

        $controller = $this->makeController(requiredRole: null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('This data source does not support showing individual entries.');

        $controller->dataSourceShow('audit-log', '1');
    }

    #[Test]
    public function dataSourceShowThrowsNotFoundWhenItemMissing(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->expects($this->once())->method('supportsAction')->with('show')->willReturn(true);
        $dataSource->expects($this->once())->method('find')->with('99')->willReturn(null);

        $this->dataSourceRegistry->method('get')->willReturn($dataSource);

        $controller = $this->makeController(requiredRole: null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Entry "99" not found in data source "audit-log".');

        $controller->dataSourceShow('audit-log', '99');
    }

    #[Test]
    public function dataSourceShowThrowsAccessDeniedBeforeLookingUpDataSourceWhenRoleRequired(): void
    {
        $this->dataSourceRegistry->method('get')
            ->willThrowException(new \LogicException('should not be called'));

        $controller = $this->makeController(requiredRole: 'ROLE_ADMIN');
        $controller->denyAccess = true;

        $this->expectException(AccessDeniedException::class);

        $controller->dataSourceShow('audit-log', '1');
    }
}

// ── Test double ──────────────────────────────────────────────────────────────

/**
 * Overrides the three AbstractController touch-points (render, isGranted,
 * denyAccessUnlessGranted) so the controller's own logic can run against
 * plain PHPUnit stubs without a real DI container.
 */
final class DataSourceControllerTestDouble extends DataSourceController
{
    /** @var array<string, bool> keyed by "attribute:subject" */
    public array $grantResults = [];

    public bool $denyAccess = false;

    /** @var array{view: string, parameters: array<string, mixed>}|null */
    public ?array $lastRender = null;

    protected function isGranted(mixed $attribute, mixed $subject = null): bool
    {
        $key = $attribute . ':' . (is_string($subject) ? $subject : 'null');

        return $this->grantResults[$key] ?? true;
    }

    protected function denyAccessUnlessGranted(mixed $attribute, mixed $subject = null, string $message = 'Access Denied.'): void
    {
        if ($this->denyAccess) {
            throw new AccessDeniedException($message);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        $this->lastRender = ['view' => $view, 'parameters' => $parameters];

        return $response ?? new Response();
    }
}

// ── Fixtures ───────────────────────────────────────────────────────────────────

#[Admin(label: 'Zebra Label', icon: 'icon-z')]
class DsCtrlFixtureZebra {}

#[Admin(label: 'Apple Label')]
class DsCtrlFixtureApple {}
