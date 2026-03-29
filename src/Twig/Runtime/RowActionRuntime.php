<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Runtime;

use Kachnitel\AdminBundle\RowAction\RowActionConditionInterface;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\RowAction\RowActionRegistry;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Twig runtime for row action rendering.
 */
class RowActionRuntime implements RuntimeExtensionInterface
{
    private LoggerInterface $logger;

    /**
     * @param ServiceLocator<object>|null $conditionLocator
     */
    public function __construct(
        private readonly RowActionRegistry $registry,
        private readonly AdminRouteRuntime $routeRuntime,
        private readonly RowActionExpressionLanguage $expressionLanguage,
        private readonly ?AuthorizationCheckerInterface $authChecker = null,
        #[AutowireLocator(RowActionConditionInterface::class)]
        private readonly ?ServiceLocator $conditionLocator = null,
        ?LoggerInterface $logger = null,
        private readonly bool $debug = false,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Get all registered actions for an entity class (unfiltered).
     *
     * @param class-string|string $entityClass
     * @return array<RowAction>
     */
    public function getRowActions(string $entityClass): array
    {
        /** @var class-string $entityClass */
        return $this->registry->getActions($entityClass);
    }

    /**
     * Get actions visible for a specific entity instance.
     * Filters by permissions, voter attributes, and conditions.
     *
     * @param class-string|string $entityClass
     * @return array<RowAction>
     */
    public function getVisibleRowActions(string $entityClass, object $entity, string $entityShortClass, string $context = ''): array
    {
        /** @var class-string $entityClass */
        $visible = [];

        foreach ($this->registry->getActions($entityClass, $context) as $action) {
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
        // 1. Voter / route check
        if (!$this->checkVoterAccess($action, $entityShortClass)) {
            return false;
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
     * Check voter / route access for the given action.
     */
    private function checkVoterAccess(RowAction $action, string $entityShortClass): bool
    {
        if ($action->voterAttribute === null) {
            return true;
        }

        if ($action->isComponentAction()) {
            return $this->authChecker === null
                || $this->authChecker->isGranted($action->voterAttribute, $entityShortClass);
        }

        $actionName = $this->mapVoterAttributeToActionName($action->voterAttribute);

        return $this->routeRuntime->isActionAccessible($entityShortClass, $actionName);
    }

    /**
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
     * @param array{0: class-string, 1: string} $condition
     *
     * @throws \RuntimeException in debug mode when the service is missing or method throws
     */
    private function evaluateDiCondition(array $condition, object $entity): bool
    {
        if ($this->conditionLocator === null) {
            return true;
        }

        [$serviceClass, $method] = $condition;

        try {
            if (!$this->conditionLocator->has($serviceClass)) {
                throw new \RuntimeException(sprintf(
                    'Condition service "%s" not found. Does it implement %s?',
                    $serviceClass,
                    RowActionConditionInterface::class,
                ));
            }

            $service = $this->conditionLocator->get($serviceClass);
            return (bool) $service->{$method}($entity);
        } catch (\Exception $e) {
            if ($this->debug) {
                throw new \RuntimeException(
                    sprintf(
                        'Row action DI condition [%s::%s] failed for entity %s: %s',
                        $serviceClass,
                        $method,
                        $entity::class,
                        $e->getMessage(),
                    ),
                    previous: $e,
                );
            }

            $this->logger->warning(
                'Row action DI condition failed — action will be hidden.',
                [
                    'service'   => $serviceClass,
                    'method'    => $method,
                    'entity'    => $entity::class,
                    'exception' => $e->getMessage(),
                ],
            );

            return false;
        }
    }

    /**
     * Map a voter attribute constant to the action name used by AdminRouteRuntime.
     */
    private function mapVoterAttributeToActionName(string $voterAttribute): string
    {
        return match ($voterAttribute) {
            'ADMIN_EDIT'    => 'edit',
            'ADMIN_SHOW'    => 'show',
            'ADMIN_DELETE'  => 'delete',
            'ADMIN_NEW'     => 'new',
            'ADMIN_ARCHIVE' => 'archive',
            default         => strtolower($voterAttribute),
        };
    }
}
