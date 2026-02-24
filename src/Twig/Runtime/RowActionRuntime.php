<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Runtime;

use Kachnitel\AdminBundle\RowAction\RowActionRegistry;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Twig runtime for row action rendering.
 *
 * Provides three Twig functions:
 *  - admin_row_actions(entityClass) — all actions (regardless of visibility)
 *  - admin_visible_row_actions(entityClass, entity, entityShortClass) — filtered for current user/entity state
 *  - admin_is_action_visible(action, entity, entityShortClass) — single-action visibility check
 */
class RowActionRuntime implements RuntimeExtensionInterface
{
    private ?PropertyAccessorInterface $propertyAccessor = null;

    public function __construct(
        private readonly RowActionRegistry $registry,
        private readonly AdminRouteRuntime $routeRuntime,
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

        return $this->evaluateExpressionCondition($condition, $entity);
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
     * Evaluate a simple property expression against an entity.
     *
     * Supported syntax:
     *   'entity.property'              — boolean check
     *   '!entity.property'             — negated boolean check
     *   'entity.property == "value"'   — equality
     *   'entity.property != "value"'   — inequality
     *   'entity.property === true'     — strict equality
     *   'entity.property !== null'     — strict inequality
     *   'entity.property > 5'          — numeric comparison
     *
     * Both 'entity.' and 'item.' prefixes are supported.
     *
     * REVIEW: use symfony's expression component - optional dep?
     */
    private function evaluateExpressionCondition(string $condition, object $entity): bool
    {
        try {
            $condition = trim($condition);

            $negated = false;
            if (str_starts_with($condition, '!')) {
                $negated = true;
                $condition = trim(substr($condition, 1));
            }

            // Parse comparison operator (longest first to avoid '!=' matching before '!==')
            $operators = ['!==', '===', '!=', '==', '>=', '<=', '>', '<'];
            $operator = null;
            $leftSide = $condition;
            $rightSide = null;

            foreach ($operators as $op) {
                if (str_contains($condition, $op)) {
                    $parts = explode($op, $condition, 2);
                    $leftSide = trim($parts[0]);
                    $rightSide = trim($parts[1]);
                    $operator = $op;
                    break;
                }
            }

            $value = $this->resolvePropertyPath($leftSide, $entity);

            if ($operator === null) {
                $result = (bool) $value;
                return $negated ? !$result : $result;
            }

            $compareValue = $this->parseLiteral($rightSide ?? '');

            $result = match ($operator) {
                '===' => $value === $compareValue,
                '!==' => $value !== $compareValue,
                '==' => $value == $compareValue,
                '!=' => $value != $compareValue,
                '>' => $value > $compareValue,
                '<' => $value < $compareValue,
                '>=' => $value >= $compareValue,
                '<=' => $value <= $compareValue,
                default => false,
            };

            return $negated ? !$result : $result;
        } catch (\Exception) {
            // If expression evaluation fails, hide the action (safe default)
            return false;
        }
    }

    /**
     * Resolve a property path like 'entity.status' or 'item.isActive' against an entity.
     */
    private function resolvePropertyPath(string $path, object $entity): mixed
    {
        if (str_starts_with($path, 'entity.')) {
            $path = substr($path, 7);
        } elseif (str_starts_with($path, 'item.')) {
            $path = substr($path, 5);
        }

        if ($this->propertyAccessor === null) {
            $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        }

        return $this->propertyAccessor->getValue($entity, $path);
    }

    /**
     * Parse a literal value from an expression string (string, bool, null, int, float).
     */
    private function parseLiteral(string $value): mixed
    {
        if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            return substr($value, 1, -1);
        }

        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => is_numeric($value)
                ? (str_contains($value, '.') ? (float) $value : (int) $value)
                : $value,
        };
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
