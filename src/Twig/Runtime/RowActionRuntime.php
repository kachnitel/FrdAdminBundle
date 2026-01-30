<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Runtime;

use Kachnitel\AdminBundle\RowAction\RowActionRegistry;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Twig runtime for row action rendering.
 */
class RowActionRuntime implements RuntimeExtensionInterface
{
    private ?PropertyAccessorInterface $propertyAccessor = null;

    public function __construct(
        private readonly RowActionRegistry $registry,
        private readonly AdminRouteRuntime $routeRuntime,
        private readonly ?AuthorizationCheckerInterface $authChecker = null,
    ) {}

    /**
     * Get all actions for an entity class.
     *
     * @return array<RowAction>
     */
    public function getRowActions(string $entityClass): array
    {
        return $this->registry->getActions($entityClass);
    }

    /**
     * Get actions visible for a specific entity instance.
     * Filters by permissions and entity-state conditions.
     *
     * @return array<RowAction>
     */
    public function getVisibleRowActions(string $entityClass, object $entity, string $entityShortClass): array
    {
        $actions = $this->registry->getActions($entityClass);
        $visible = [];

        foreach ($actions as $action) {
            if ($this->isActionVisible($action, $entity, $entityShortClass)) {
                $visible[] = $action;
            }
        }

        return $visible;
    }

    /**
     * Check if an action should be visible for a specific entity.
     */
    public function isActionVisible(RowAction $action, object $entity, string $entityShortClass): bool
    {
        // Check voter attribute (uses existing AdminEntityVoter via AdminRouteRuntime)
        if ($action->voterAttribute !== null) {
            $actionName = $this->mapVoterToAction($action->voterAttribute);
            if (!$this->routeRuntime->isActionAccessible($entityShortClass, $actionName)) {
                return false;
            }
        }

        // Check direct permission/role
        if ($action->permission !== null && $this->authChecker !== null) {
            if (!$this->authChecker->isGranted($action->permission)) {
                return false;
            }
        }

        // Check condition expression
        if ($action->condition !== null) {
            if (!$this->evaluateCondition($action->condition, $entity)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Map voter attribute to action name for route checking.
     */
    private function mapVoterToAction(string $voterAttribute): string
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

    /**
     * Evaluate a condition expression against an entity.
     *
     * Supports simple conditions:
     * - "entity.property" or "item.property" - boolean check
     * - "entity.property == value" - equality check
     * - "entity.property != value" - inequality check
     * - "entity.property === value" - strict equality
     * - "entity.property !== value" - strict inequality
     * - "!entity.property" - negated boolean check
     */
    private function evaluateCondition(string $condition, object $entity): bool
    {
        try {
            $condition = trim($condition);

            // Handle negation
            $negated = false;
            if (str_starts_with($condition, '!')) {
                $negated = true;
                $condition = trim(substr($condition, 1));
            }

            // Parse comparison operators
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

            // Get the property value from entity
            $value = $this->getPropertyValue($leftSide, $entity);

            // Simple boolean check (no operator)
            if ($operator === null) {
                $result = (bool) $value;
                return $negated ? !$result : $result;
            }

            // Parse the right side value
            $compareValue = $this->parseValue($rightSide);

            // Perform comparison
            /** @var string $operator */
            $result = match ($operator) {
                '===' => $value === $compareValue,
                '!==' => $value !== $compareValue,
                '==' => $value == $compareValue,
                '!=' => $value != $compareValue,
                '>' => $value > $compareValue,
                '<' => $value < $compareValue,
                '>=' => $value >= $compareValue,
                '<=' => $value <= $compareValue,
            };

            return $negated ? !$result : $result;
        } catch (\Exception) {
            // If condition evaluation fails, hide the action (safe default)
            return false;
        }
    }

    /**
     * Get a property value from the entity using property path.
     */
    private function getPropertyValue(string $path, object $entity): mixed
    {
        // Remove "entity." or "item." prefix
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
     * Parse a value from the condition string.
     */
    private function parseValue(string $value): mixed
    {
        // Handle quoted strings
        if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            return substr($value, 1, -1);
        }

        // Handle special values
        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => is_numeric($value) ? (str_contains($value, '.') ? (float) $value : (int) $value) : $value,
        };
    }
}
