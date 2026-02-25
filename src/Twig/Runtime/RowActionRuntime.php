<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Runtime;

use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\RowAction\RowActionRegistry;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Twig runtime for row action rendering.
 *
 * Provides three Twig functions:
 *  - admin_row_actions(entityClass) — all actions (regardless of visibility)
 *  - admin_visible_row_actions(entityClass, entity, entityShortClass) — filtered for current user/entity state
 *  - admin_is_action_visible(action, entity, entityShortClass) — single-action visibility check
 *
 * String condition expressions are evaluated via Symfony's ExpressionLanguage component (see
 * RowActionExpressionLanguage). Supported syntax includes property comparisons, logical operators
 * (&&, ||, and, or, not, !), and the is_granted() security function:
 *
 *   entity.status == "pending"
 *   entity.stock > 0 && is_granted("ROLE_EDITOR")
 *   is_granted("ROLE_ADMIN") || entity.ownedBy == currentUser
 *
 * DI-tuple conditions ([ServiceClass::class, 'method']) are resolved from the service container.
 */
class RowActionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly RowActionRegistry $registry,
        private readonly AdminRouteRuntime $routeRuntime,
        private readonly RowActionExpressionLanguage $expressionLanguage,
        private readonly ?AuthorizationCheckerInterface $authChecker = null,
        private readonly ?ContainerInterface $container = null,
    ) {}

    /**
     * Get all registered actions for an entity class (unfiltered).
     *
     * @return array<RowAction>
     */
    public function getRowActions(string $entityClass): array
    {
        return $this->registry->getActions($entityClass);
    }

    /**
     * Get actions visible for a specific entity instance.
     * Filters by permissions, voter attributes, and conditions.
     *
     * @return array<RowAction>
     */
    public function getVisibleRowActions(string $entityClass, object $entity, string $entityShortClass): array
    {
        $visible = [];

        foreach ($this->registry->getActions($entityClass) as $action) {
            if ($this->isActionVisible($action, $entity, $entityShortClass)) {
                $visible[] = $action;
            }
        }

        return $visible;
    }

    /**
     * Check if a single action should be visible for a specific entity.
     */
    public function isActionVisible(RowAction $action, object $entity, string $entityShortClass): bool
    {
        // 1. Voter attribute — delegates to AdminEntityVoter via AdminRouteRuntime
        if ($action->voterAttribute !== null) {
            $actionName = $this->mapVoterAttributeToActionName($action->voterAttribute);
            if (!$this->routeRuntime->isActionAccessible($entityShortClass, $actionName)) {
                return false;
            }
        }

        // 2. Direct permission/role check
        if ($action->permission !== null && $this->authChecker !== null) {
            if (!$this->authChecker->isGranted($action->permission)) {
                return false;
            }
        }

        // 3. Condition — string expression or [ServiceClass::class, 'method'] DI tuple
        if ($action->condition !== null) {
            if (!$this->evaluateCondition($action->condition, $entity)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a condition, dispatching to the appropriate evaluator based on type.
     *
     * @param string|array{0: class-string, 1: string} $condition
     */
    private function evaluateCondition(string|array $condition, object $entity): bool
    {
        if (is_array($condition)) {
            return $this->evaluateDiCondition($condition, $entity);
        }

        return $this->expressionLanguage->evaluate($condition, $entity, $this->authChecker);
    }

    /**
     * Resolve and invoke a [ServiceClass::class, 'method'] tuple from the DI container.
     * The method receives the entity object and must return bool.
     *
     * @param array{0: class-string, 1: string} $condition
     */
    private function evaluateDiCondition(array $condition, object $entity): bool
    {
        if ($this->container === null) {
            // No container available — fail open (show the action) so misconfiguration
            // doesn't silently hide actions in environments without the container.
            return true;
        }

        [$serviceClass, $method] = $condition;

        try {
            $service = $this->container->get($serviceClass);
            return (bool) $service->{$method}($entity);
        } catch (\Exception) {
            // Service not found or method threw — hide the action (safe default)
            return false;
        }
    }

    /**
     * Map an AdminEntityVoter attribute constant to the route action name used by AdminRouteRuntime.
     */
    private function mapVoterAttributeToActionName(string $voterAttribute): string
    {
        return match ($voterAttribute) {
            'ADMIN_INDEX' => 'index',
            'ADMIN_SHOW' => 'show',
            'ADMIN_NEW' => 'new',
            'ADMIN_EDIT' => 'edit',
            'ADMIN_DELETE' => 'delete',
            default => 'show',
        };
    }
}