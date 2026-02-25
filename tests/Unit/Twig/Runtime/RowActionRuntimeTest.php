<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Kachnitel\AdminBundle\RowAction\RowActionRegistry;
use Kachnitel\AdminBundle\Tests\Unit\ValueObject\ApprovalService;
use Kachnitel\AdminBundle\Twig\Runtime\AdminRouteRuntime;
use Kachnitel\AdminBundle\Twig\Runtime\RowActionRuntime;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @group row-actions
 */
class RowActionRuntimeTest extends TestCase
{
    /** @var RowActionRegistry&MockObject */
    private RowActionRegistry $registry;

    /** @var AdminRouteRuntime&MockObject */
    private AdminRouteRuntime $routeRuntime;

    /** @var AuthorizationCheckerInterface&MockObject */
    private AuthorizationCheckerInterface $authChecker;

    /** @var ContainerInterface&MockObject */
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(RowActionRegistry::class);
        $this->routeRuntime = $this->createMock(AdminRouteRuntime::class);
        $this->authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->container = $this->createMock(ContainerInterface::class);
    }

    private function createRuntime(
        bool $withAuthChecker = true,
        bool $withContainer = true,
    ): RowActionRuntime {
        return new RowActionRuntime(
            registry: $this->registry,
            routeRuntime: $this->routeRuntime,
            authChecker: $withAuthChecker ? $this->authChecker : null,
            container: $withContainer ? $this->container : null,
        );
    }

    private function makeEntity(mixed $status = 'pending', bool $active = true): object
    {
        return new class ($status, $active) {
            public function __construct(
                public readonly mixed $status,
                public readonly bool $active,
            ) {}

            public function getStatus(): mixed { return $this->status; }
            public function isActive(): bool { return $this->active; }
        };
    }

    // -------------------------------------------------------------------------
    // String expression conditions
    // -------------------------------------------------------------------------

    /** @test */
    public function expressionEqualityHidesActionWhenFalse(): void
    {
        $entity = $this->makeEntity(status: 'archived');
        $action = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: 'entity.status == "pending"',
        );

        $runtime = $this->createRuntime();
        $this->assertFalse($runtime->isActionVisible($action, $entity, 'Product'));
    }

    /** @test */
    public function expressionEqualityShowsActionWhenTrue(): void
    {
        $entity = $this->makeEntity(status: 'pending');
        $action = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: 'entity.status == "pending"',
        );

        $runtime = $this->createRuntime();
        $this->assertTrue($runtime->isActionVisible($action, $entity, 'Product'));
    }

    /** @test */
    public function expressionNegationWorks(): void
    {
        $entity = $this->makeEntity(active: false);
        $action = new RowAction(name: 'edit', label: 'Edit', condition: '!entity.active');

        $runtime = $this->createRuntime();
        // active = false → !false = true → show
        $this->assertTrue($runtime->isActionVisible($action, $entity, 'Product'));
    }

    /** @test */
    public function expressionBooleanCheck(): void
    {
        $entity = $this->makeEntity(active: true);
        $action = new RowAction(name: 'edit', label: 'Edit', condition: 'entity.active');

        $runtime = $this->createRuntime();
        $this->assertTrue($runtime->isActionVisible($action, $entity, 'Product'));
    }

    /** @test */
    public function expressionInequalityCheck(): void
    {
        $entity = $this->makeEntity(status: 'archived');
        $action = new RowAction(name: 'edit', label: 'Edit', condition: 'entity.status != "archived"');

        $runtime = $this->createRuntime();
        $this->assertFalse($runtime->isActionVisible($action, $entity, 'Product'));
    }

    /** @test */
    public function expressionHidesActionOnEvaluationError(): void
    {
        $entity = $this->makeEntity();
        // Non-existent property — should fail silently and hide
        $action = new RowAction(name: 'edit', label: 'Edit', condition: 'entity.nonExistentProperty == true');

        $runtime = $this->createRuntime();
        $this->assertFalse($runtime->isActionVisible($action, $entity, 'Product'));
    }

    // -------------------------------------------------------------------------
    // DI tuple conditions
    // -------------------------------------------------------------------------

    /** @test */
    public function diTupleHidesActionWhenServiceReturnsFalse(): void
    {
        $entity = $this->makeEntity();

        $conditionService = new class () {
            public function canApprove(object $entity): bool { return false; }
        };

        $this->container->method('get')->willReturn($conditionService);

        /** @var class-string $serviceClass */
        $serviceClass = get_class($conditionService);
        $action = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: [$serviceClass, 'canApprove'],
        );

        $runtime = $this->createRuntime();
        $this->assertFalse($runtime->isActionVisible($action, $entity, 'Product'));
    }

    /** @test */
    public function diTupleShowsActionWhenServiceReturnsTrue(): void
    {
        $entity = $this->makeEntity();

        $conditionService = new class () {
            public function canApprove(object $entity): bool { return true; }
        };

        $this->container->method('get')->willReturn($conditionService);
        $this->routeRuntime->method('isActionAccessible')->willReturn(true);

        /** @var class-string $serviceClass */
        $serviceClass = get_class($conditionService);
        $action = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: [$serviceClass, 'canApprove'],
        );

        $runtime = $this->createRuntime();
        $this->assertTrue($runtime->isActionVisible($action, $entity, 'Product'));
    }

    /** @test */
    public function diTupleReceivesEntityObject(): void
    {
        $entity = $this->makeEntity(status: 'pending');

        $conditionService = new class () {
            public mixed $received = null;
            public function check(object $entity): bool
            {
                $this->received = $entity;
                return true;
            }
        };

        $this->container->method('get')->willReturn($conditionService);

        /** @var class-string $serviceClass */
        $serviceClass = get_class($conditionService);
        $action = new RowAction(
            name: 'check',
            label: 'Check',
            condition: [$serviceClass, 'check'],
        );

        $runtime = $this->createRuntime();
        $runtime->isActionVisible($action, $entity, 'Product');

        $this->assertSame($entity, $conditionService->received);
    }

    /** @test */
    public function diTupleFailsOpenWhenContainerNotAvailable(): void
    {
        $entity = $this->makeEntity();
        /** @var array{class-string, string} $condition */
        $condition = [ApprovalService::class, 'canApprove'];
        $action = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: $condition,
        );

        // Runtime without container
        $runtime = $this->createRuntime(withContainer: false);
        // No container → fail open (show action, don't hide silently)
        $this->assertTrue($runtime->isActionVisible($action, $entity, 'Product'));
    }

    /** @test */
    public function diTupleHidesActionWhenServiceThrows(): void
    {
        $entity = $this->makeEntity();

        $this->container->method('get')->willThrowException(new \RuntimeException('Service not found'));

        /** @var array{class-string, string} $condition */
        $condition = ['App\\Service\\MissingService', 'canApprove']; // @phpstan-ignore varTag.nativeType
        $action = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: $condition,
        );

        $runtime = $this->createRuntime();
        $this->assertFalse($runtime->isActionVisible($action, $entity, 'Product'));
    }

    // -------------------------------------------------------------------------
    // Permission / voter checks
    // -------------------------------------------------------------------------

    /** @test */
    public function voterAttributeHidesActionWhenNotAccessible(): void
    {
        $entity = $this->makeEntity();
        $this->routeRuntime
            ->method('isActionAccessible')
            ->with('Product', 'edit')
            ->willReturn(false);

        $action = new RowAction(name: 'edit', label: 'Edit', voterAttribute: 'ADMIN_EDIT');

        $runtime = $this->createRuntime();
        $this->assertFalse($runtime->isActionVisible($action, $entity, 'Product'));
    }

    /** @test */
    public function directPermissionHidesActionWhenNotGranted(): void
    {
        $entity = $this->makeEntity();
        $this->authChecker->method('isGranted')->with('ROLE_MANAGER')->willReturn(false);

        $action = new RowAction(name: 'promote', label: 'Promote', permission: 'ROLE_MANAGER');

        $runtime = $this->createRuntime();
        $this->assertFalse($runtime->isActionVisible($action, $entity, 'Product'));
    }

    /** @test */
    public function actionVisibleWhenNoConstraints(): void
    {
        $entity = $this->makeEntity();
        $action = new RowAction(name: 'show', label: 'Show');

        $runtime = $this->createRuntime();
        $this->assertTrue($runtime->isActionVisible($action, $entity, 'Product'));
    }

    // -------------------------------------------------------------------------
    // getVisibleRowActions
    // -------------------------------------------------------------------------

    /** @test */
    public function getVisibleRowActionsFiltersActions(): void
    {
        $entity = $this->makeEntity(active: true);

        $this->registry->method('getActions')->willReturn([
            new RowAction(name: 'show', label: 'Show', condition: 'entity.active'),
            new RowAction(name: 'archive', label: 'Archive', condition: '!entity.active'),
        ]);

        $runtime = $this->createRuntime();
        $visible = $runtime->getVisibleRowActions('App\\Entity\\Product', $entity, 'Product');

        $this->assertCount(1, $visible);
        $this->assertSame('show', $visible[0]->name);
    }

    /** @test */
    public function getRowActionsReturnsAllUnfiltered(): void
    {
        $actions = [
            new RowAction(name: 'show', label: 'Show'),
            new RowAction(name: 'edit', label: 'Edit'),
        ];
        $this->registry->method('getActions')->willReturn($actions);

        $runtime = $this->createRuntime();
        $this->assertSame($actions, $runtime->getRowActions('App\\Entity\\Product'));
    }
}
