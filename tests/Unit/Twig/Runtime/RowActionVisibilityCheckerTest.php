<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Kachnitel\AdminBundle\Tests\Unit\ValueObject\ApprovalService;
use Kachnitel\AdminBundle\Twig\Runtime\AdminRouteRuntime;
use Kachnitel\AdminBundle\Twig\Runtime\RowActionVisibilityChecker;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;

/**
 * Covers RowActionVisibilityChecker — the visibility-decision logic
 * (class-level voter/route access, object-level authorization, direct
 * permission/role checks, string-expression and DI-tuple conditions)
 * extracted from RowActionRuntime to keep coupling and cyclomatic
 * complexity under PHPMD's thresholds. See that class's docblock.
 *
 * Registry-delegation behaviour (getRowActions/getVisibleRowActions, and
 * isActionVisible()'s delegation to this class) is covered separately in
 * RowActionRuntimeTest, which mocks this class rather than duplicating its
 * decision-logic assertions.
 */
#[AllowMockObjectsWithoutExpectations]
#[Group('row-actions')]
final class RowActionVisibilityCheckerTest extends TestCase
{
    /** @var AdminRouteRuntime&MockObject */
    private AdminRouteRuntime $routeRuntime;

    /** @var AuthorizationCheckerInterface&MockObject */
    private AuthorizationCheckerInterface $authChecker;

    /** @var ServiceLocator<object>&MockObject */
    private ServiceLocator $conditionLocator;

    private RowActionExpressionLanguage $expressionLanguage;

    protected function setUp(): void
    {
        $this->routeRuntime = $this->createMock(AdminRouteRuntime::class);
        $this->authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->conditionLocator = $this->createMock(ServiceLocator::class);
        $this->expressionLanguage = new RowActionExpressionLanguage(new ExpressionLanguage());
    }

    private function createChecker(
        bool $withAuthChecker = true,
        bool $withContainer = true,
        ?ObjectAuthorizationChecker $objectAuthChecker = null,
    ): RowActionVisibilityChecker {
        return new RowActionVisibilityChecker(
            routeRuntime: $this->routeRuntime,
            expressionLanguage: $this->expressionLanguage,
            authChecker: $withAuthChecker ? $this->authChecker : null,
            conditionLocator: $withContainer ? $this->conditionLocator : null,
            objectAuthChecker: $objectAuthChecker,
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

    #[Test]
    public function expressionEqualityHidesActionWhenFalse(): void
    {
        $entity = $this->makeEntity(status: 'archived');
        $action = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: 'entity.status == "pending"',
        );

        $checker = $this->createChecker();
        $this->assertFalse($checker->isVisible($action, $entity, 'Product'));
    }

    #[Test]
    public function expressionEqualityShowsActionWhenTrue(): void
    {
        $entity = $this->makeEntity(status: 'pending');
        $action = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: 'entity.status == "pending"',
        );

        $checker = $this->createChecker();
        $this->assertTrue($checker->isVisible($action, $entity, 'Product'));
    }

    #[Test]
    public function expressionNegationWorks(): void
    {
        $entity = $this->makeEntity(active: false);
        $action = new RowAction(name: 'edit', label: 'Edit', condition: '!entity.active');

        $checker = $this->createChecker();
        // active = false → !false = true → show
        $this->assertTrue($checker->isVisible($action, $entity, 'Product'));
    }

    #[Test]
    public function expressionBooleanCheck(): void
    {
        $entity = $this->makeEntity(active: true);
        $action = new RowAction(name: 'edit', label: 'Edit', condition: 'entity.active');

        $checker = $this->createChecker();
        $this->assertTrue($checker->isVisible($action, $entity, 'Product'));
    }

    #[Test]
    public function expressionInequalityCheck(): void
    {
        $entity = $this->makeEntity(status: 'archived');
        $action = new RowAction(name: 'edit', label: 'Edit', condition: 'entity.status != "archived"');

        $checker = $this->createChecker();
        $this->assertFalse($checker->isVisible($action, $entity, 'Product'));
    }

    #[Test]
    public function expressionHidesActionOnEvaluationError(): void
    {
        $entity = $this->makeEntity();
        // Non-existent property — should fail silently and hide
        $action = new RowAction(name: 'edit', label: 'Edit', condition: 'entity.nonExistentProperty == true');

        $checker = $this->createChecker();
        $this->assertFalse($checker->isVisible($action, $entity, 'Product'));
    }

    // -------------------------------------------------------------------------
    // Combining conditions (&&, ||)
    // -------------------------------------------------------------------------

    #[Test]
    public function andCombinationRequiresBothConditionsTrue(): void
    {
        $entity = $this->makeEntity(status: 'pending', active: true);
        $action = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: 'entity.status == "pending" && entity.active',
        );

        $checker = $this->createChecker();
        $this->assertTrue($checker->isVisible($action, $entity, 'Order'));
    }

    #[Test]
    public function andCombinationHidesActionWhenOneConditionFails(): void
    {
        $entity = $this->makeEntity(status: 'archived', active: true);
        $action = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: 'entity.status == "pending" && entity.active',
        );

        $checker = $this->createChecker();
        $this->assertFalse($checker->isVisible($action, $entity, 'Order'));
    }

    #[Test]
    public function orCombinationShowsActionWhenEitherTrue(): void
    {
        $entity = $this->makeEntity(status: 'archived', active: true);
        $action = new RowAction(
            name: 'view',
            label: 'View',
            condition: 'entity.status == "pending" || entity.active',
        );

        $checker = $this->createChecker();
        $this->assertTrue($checker->isVisible($action, $entity, 'Order'));
    }

    #[Test]
    public function orCombinationHidesActionWhenBothFalse(): void
    {
        $entity = $this->makeEntity(status: 'archived', active: false);
        $action = new RowAction(
            name: 'view',
            label: 'View',
            condition: 'entity.status == "pending" || entity.active',
        );

        $checker = $this->createChecker();
        $this->assertFalse($checker->isVisible($action, $entity, 'Order'));
    }

    // -------------------------------------------------------------------------
    // is_granted() in expressions
    // -------------------------------------------------------------------------

    #[Test]
    public function isGrantedInExpressionShowsActionWhenRoleGranted(): void
    {
        $this->authChecker->expects($this->once())->method('isGranted')->with('ROLE_EDITOR', null)->willReturn(true);

        $entity = $this->makeEntity();
        $action = new RowAction(
            name: 'promote',
            label: 'Promote',
            condition: 'is_granted("ROLE_EDITOR")',
        );

        $checker = $this->createChecker();
        $this->assertTrue($checker->isVisible($action, $entity, 'User'));
    }

    #[Test]
    public function isGrantedInExpressionHidesActionWhenRoleNotGranted(): void
    {
        $this->authChecker->expects($this->once())->method('isGranted')->with('ROLE_SUPER_ADMIN', null)->willReturn(false);

        $entity = $this->makeEntity();
        $action = new RowAction(
            name: 'impersonate',
            label: 'Impersonate',
            condition: 'is_granted("ROLE_SUPER_ADMIN")',
        );

        $checker = $this->createChecker();
        $this->assertFalse($checker->isVisible($action, $entity, 'User'));
    }

    #[Test]
    public function isGrantedCombinedWithPropertyCondition(): void
    {
        $this->authChecker->expects($this->once())->method('isGranted')->with('ROLE_EDITOR', null)->willReturn(true);

        $entity = $this->makeEntity(status: 'pending');
        $action = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: 'entity.status == "pending" && is_granted("ROLE_EDITOR")',
        );

        $checker = $this->createChecker();
        $this->assertTrue($checker->isVisible($action, $entity, 'Order'));
    }

    #[Test]
    public function isGrantedCombinedFalseWhenPropertyConditionFails(): void
    {
        $this->authChecker->expects($this->never())->method('isGranted')->with('ROLE_EDITOR', null)->willReturn(true); // REVIEW:

        $entity = $this->makeEntity(status: 'archived');
        $action = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: 'entity.status == "pending" && is_granted("ROLE_EDITOR")',
        );

        $checker = $this->createChecker();
        $this->assertFalse($checker->isVisible($action, $entity, 'Order'));
    }

    #[Test]
    public function isGrantedReturnsFalseWhenNoAuthCheckerProvided(): void
    {
        $entity = $this->makeEntity();
        $action = new RowAction(
            name: 'admin',
            label: 'Admin only',
            condition: 'is_granted("ROLE_ADMIN")',
        );

        $checker = $this->createChecker(withAuthChecker: false);
        $this->assertFalse($checker->isVisible($action, $entity, 'Product'));
    }

    // -------------------------------------------------------------------------
    // DI tuple conditions
    // -------------------------------------------------------------------------

    #[Test]
    public function diTupleHidesActionWhenServiceReturnsFalse(): void
    {
        $entity = $this->makeEntity();

        $conditionService = new class () {
            public function canApprove(object $entity): bool { return false; }
        };

        $this->conditionLocator->method('has')->willReturn(true);
        $this->conditionLocator->method('get')->willReturn($conditionService);

        /** @var class-string $serviceClass */
        $serviceClass = get_class($conditionService);
        $action = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: [$serviceClass, 'canApprove'],
        );

        $checker = $this->createChecker();
        $this->assertFalse($checker->isVisible($action, $entity, 'Product'));
    }

    #[Test]
    public function diTupleShowsActionWhenServiceReturnsTrue(): void
    {
        $entity = $this->makeEntity();

        $conditionService = new class () {
            public function canApprove(object $entity): bool { return true; }
        };

        $this->conditionLocator->method('has')->willReturn(true);
        $this->conditionLocator->method('get')->willReturn($conditionService);
        $this->routeRuntime->method('isActionAccessible')->willReturn(true);

        /** @var class-string $serviceClass */
        $serviceClass = get_class($conditionService);
        $action = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: [$serviceClass, 'canApprove'],
        );

        $checker = $this->createChecker();
        $this->assertTrue($checker->isVisible($action, $entity, 'Product'));
    }

    #[Test]
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

        $this->conditionLocator->method('has')->willReturn(true);
        $this->conditionLocator->method('get')->willReturn($conditionService);

        /** @var class-string $serviceClass */
        $serviceClass = get_class($conditionService);
        $action = new RowAction(
            name: 'check',
            label: 'Check',
            condition: [$serviceClass, 'check'],
        );

        $checker = $this->createChecker();
        $checker->isVisible($action, $entity, 'Product');

        $this->assertSame($entity, $conditionService->received);
    }

    #[Test]
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

        // Checker without container
        $checker = $this->createChecker(withContainer: false);
        // No container → fail open (show action, don't hide silently)
        $this->assertTrue($checker->isVisible($action, $entity, 'Product'));
    }

    #[Test]
    public function diTupleHidesActionWhenServiceThrows(): void
    {
        $entity = $this->makeEntity();

        $this->conditionLocator->method('has')->willReturn(false);

        /** @var array{class-string, string} $condition */
        $condition = ['App\\Service\\MissingService', 'canApprove']; // @phpstan-ignore varTag.nativeType
        $action = new RowAction(
            name: 'approve',
            label: 'Approve',
            condition: $condition,
        );

        $checker = $this->createChecker();
        $this->assertFalse($checker->isVisible($action, $entity, 'Product'));
    }

    // -------------------------------------------------------------------------
    // Permission / voter checks
    // -------------------------------------------------------------------------

    #[Test]
    public function voterAttributeHidesActionWhenNotAccessible(): void
    {
        $entity = $this->makeEntity();
        $this->routeRuntime
            ->expects($this->once())->method('isActionAccessible')
            ->with('Product', 'edit')
            ->willReturn(false);

        $action = new RowAction(name: 'edit', label: 'Edit', voterAttribute: 'ADMIN_EDIT');

        $checker = $this->createChecker();
        $this->assertFalse($checker->isVisible($action, $entity, 'Product'));
    }

    #[Test]
    public function directPermissionHidesActionWhenNotGranted(): void
    {
        $entity = $this->makeEntity();
        $this->authChecker->expects($this->once())->method('isGranted')->with('ROLE_MANAGER')->willReturn(false);

        $action = new RowAction(name: 'promote', label: 'Promote', permission: 'ROLE_MANAGER');

        $checker = $this->createChecker();
        $this->assertFalse($checker->isVisible($action, $entity, 'Product'));
    }

    #[Test]
    public function actionVisibleWhenNoConstraints(): void
    {
        $entity = $this->makeEntity();
        $action = new RowAction(name: 'show', label: 'Show');

        $checker = $this->createChecker();
        $this->assertTrue($checker->isVisible($action, $entity, 'Product'));
    }

    // -------------------------------------------------------------------------
    // Object-level authorization
    // -------------------------------------------------------------------------

    #[Test]
    public function objectAuthorizationHidesActionWhenDeniedForThisEntity(): void
    {
        $entity = $this->makeEntity();
        $this->routeRuntime->method('isActionAccessible')->willReturn(true); // class-level passes

        $objectAuthChecker = $this->createMock(ObjectAuthorizationChecker::class);
        $objectAuthChecker->expects($this->once())
            ->method('isGranted')
            ->with('ADMIN_EDIT', $entity)
            ->willReturn(false);

        $action = new RowAction(name: 'edit', label: 'Edit', voterAttribute: 'ADMIN_EDIT');

        $checker = $this->createChecker(objectAuthChecker: $objectAuthChecker);
        $this->assertFalse($checker->isVisible($action, $entity, 'Product'));
    }

    #[Test]
    public function objectAuthorizationShowsActionWhenGrantedForThisEntity(): void
    {
        $entity = $this->makeEntity();
        $this->routeRuntime->method('isActionAccessible')->willReturn(true);

        $objectAuthChecker = $this->createMock(ObjectAuthorizationChecker::class);
        $objectAuthChecker->expects($this->once())
            ->method('isGranted')
            ->with('ADMIN_EDIT', $entity)
            ->willReturn(true);

        $action = new RowAction(name: 'edit', label: 'Edit', voterAttribute: 'ADMIN_EDIT');

        $checker = $this->createChecker(objectAuthChecker: $objectAuthChecker);
        $this->assertTrue($checker->isVisible($action, $entity, 'Product'));
    }

    #[Test]
    public function objectAuthorizationIsSkippedWhenActionHasNoVoterAttribute(): void
    {
        $entity = $this->makeEntity();

        $objectAuthChecker = $this->createMock(ObjectAuthorizationChecker::class);
        $objectAuthChecker->expects($this->never())->method('isGranted');

        // No voterAttribute — nothing to check ObjectAuthorizationChecker against.
        $action = new RowAction(name: 'custom', label: 'Custom');

        $checker = $this->createChecker(objectAuthChecker: $objectAuthChecker);
        $this->assertTrue($checker->isVisible($action, $entity, 'Product'));
    }

    /**
     * Backward-compatibility guard: a checker constructed the old way — no
     * objectAuthChecker at all, which is what every other test in this file
     * does via the bare createChecker() call — must keep behaving exactly
     * as it did before this dependency existed.
     */
    #[Test]
    public function objectAuthorizationIsSkippedWhenCheckerNotProvided(): void
    {
        $entity = $this->makeEntity();
        $this->routeRuntime->method('isActionAccessible')->willReturn(true);

        $action = new RowAction(name: 'edit', label: 'Edit', voterAttribute: 'ADMIN_EDIT');

        $checker = $this->createChecker();
        $this->assertTrue($checker->isVisible($action, $entity, 'Product'));
    }

    /**
     * Proves the two gates are independent, ordered, AND'ed checks rather
     * than a single merged decision: a class-level denial short-circuits
     * before object-level authorization is ever consulted.
     */
    #[Test]
    public function classLevelDenialShortCircuitsBeforeObjectLevelIsConsulted(): void
    {
        $entity = $this->makeEntity();
        $this->routeRuntime->method('isActionAccessible')->willReturn(false);

        $objectAuthChecker = $this->createMock(ObjectAuthorizationChecker::class);
        $objectAuthChecker->expects($this->never())->method('isGranted');

        $action = new RowAction(name: 'edit', label: 'Edit', voterAttribute: 'ADMIN_EDIT');

        $checker = $this->createChecker(objectAuthChecker: $objectAuthChecker);
        $this->assertFalse($checker->isVisible($action, $entity, 'Product'));
    }

    // -------------------------------------------------------------------------
    // Debug-mode DI-tuple failures
    // -------------------------------------------------------------------------

    #[Test]
    public function diTupleThrowsInDebugModeWhenServiceNotFound(): void
    {
        $entity = $this->makeEntity();

        $this->conditionLocator->method('has')->willReturn(false);

        /** @var array{class-string, string} $condition */
        $condition = ['App\\Service\\BrokenService', 'check']; // @phpstan-ignore varTag.nativeType
        $action = new RowAction(name: 'broken', label: 'Broken', condition: $condition);

        $checker = new RowActionVisibilityChecker(
            routeRuntime: $this->routeRuntime,
            expressionLanguage: $this->expressionLanguage,
            authChecker: $this->authChecker,
            conditionLocator: $this->conditionLocator,
            debug: true,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Row action DI condition \[App\\\\Service\\\\BrokenService::check\]/');

        $checker->isVisible($action, $entity, 'Product');
    }

    #[Test]
    public function diTupleThrowsInDebugModeWhenServiceNotInLocator(): void
    {
        $entity = $this->makeEntity();

        $this->conditionLocator->method('has')->willReturn(false);

        /** @var array{class-string, string} $condition */
        $condition = ['App\\Service\\BrokenService', 'check']; // @phpstan-ignore varTag.nativeType
        $action = new RowAction(name: 'broken', label: 'Broken', condition: $condition);

        $checker = new RowActionVisibilityChecker(
            routeRuntime: $this->routeRuntime,
            expressionLanguage: $this->expressionLanguage,
            authChecker: $this->authChecker,
            conditionLocator: $this->conditionLocator,
            debug: true,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/App\\\\Service\\\\BrokenService/');

        $checker->isVisible($action, $entity, 'Product');
    }

    #[Test]
    public function diTupleThrowsInDebugModeWhenMethodThrows(): void
    {
        $entity = $this->makeEntity();

        $conditionService = new class () {
            public function canDo(object $entity): bool
            {
                throw new \LogicException('Business rule violated');
            }
        };

        $this->conditionLocator->method('has')->willReturn(true);
        $this->conditionLocator->method('get')->willReturn($conditionService);

        /** @var class-string $serviceClass */
        $serviceClass = get_class($conditionService);
        $action = new RowAction(name: 'do', label: 'Do', condition: [$serviceClass, 'canDo']);

        $checker = new RowActionVisibilityChecker(
            routeRuntime: $this->routeRuntime,
            expressionLanguage: $this->expressionLanguage,
            conditionLocator: $this->conditionLocator,
            debug: true,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Business rule violated/');

        $checker->isVisible($action, $entity, 'Product');
    }

    #[Test]
    public function diTupleDebugExceptionWrapsOriginal(): void
    {
        $entity = $this->makeEntity();
        $original = new \LogicException('root cause');

        $this->conditionLocator->method('has')->willReturn(true);
        $this->conditionLocator->method('get')->willThrowException($original);

        /** @var array{class-string, string} $condition */
        $condition = ['App\\Service\\BrokenService', 'check']; // @phpstan-ignore varTag.nativeType
        $action = new RowAction(name: 'broken', label: 'Broken', condition: $condition);

        $checker = new RowActionVisibilityChecker(
            routeRuntime: $this->routeRuntime,
            expressionLanguage: $this->expressionLanguage,
            conditionLocator: $this->conditionLocator,
            debug: true,
        );

        try {
            $checker->isVisible($action, $entity, 'Product');
            $this->fail('Expected \RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }

    #[Test]
    public function diTupleLogsWarningInProdModeAndHidesAction(): void
    {
        $entity = $this->makeEntity();

        $this->conditionLocator->method('has')->willReturn(false); // not registered

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('Row action DI condition failed'),
                $this->logicalAnd(
                    $this->arrayHasKey('service'),
                    $this->arrayHasKey('method'),
                    $this->arrayHasKey('entity'),
                    $this->arrayHasKey('exception'),
                ),
            );

        /** @var array{class-string, string} $condition */
        $condition = ['App\\Service\\BrokenService', 'check']; // @phpstan-ignore varTag.nativeType
        $action = new RowAction(name: 'broken', label: 'Broken', condition: $condition);

        $checker = new RowActionVisibilityChecker(
            routeRuntime: $this->routeRuntime,
            expressionLanguage: $this->expressionLanguage,
            conditionLocator: $this->conditionLocator,
            logger: $logger,
            debug: false,
        );

        $this->assertFalse($checker->isVisible($action, $entity, 'Product'));
    }

    #[Test]
    public function noLogAndNoThrowWhenDiConditionSucceeds(): void
    {
        $entity = $this->makeEntity();

        $conditionService = new class () {
            public function canDo(object $entity): bool { return true; }
        };

        $this->conditionLocator->method('has')->willReturn(true);
        $this->conditionLocator->method('get')->willReturn($conditionService);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        /** @var class-string $serviceClass */
        $serviceClass = get_class($conditionService);
        $action = new RowAction(name: 'do', label: 'Do', condition: [$serviceClass, 'canDo']);

        foreach ([true, false] as $debug) {
            $checker = new RowActionVisibilityChecker(
                routeRuntime: $this->routeRuntime,
                expressionLanguage: $this->expressionLanguage,
                conditionLocator: $this->conditionLocator,
                logger: $logger,
                debug: $debug,
            );

            $this->assertTrue($checker->isVisible($action, $entity, 'Product'));
        }
    }
}
