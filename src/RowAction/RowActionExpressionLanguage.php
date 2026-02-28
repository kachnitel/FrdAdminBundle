<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Evaluates row-action visibility expressions using Symfony's ExpressionLanguage component.
 *
 * Entity properties are accessed via Symfony's PropertyAccess component, so `entity.status`
 * calls `getStatus()`, `isStatus()`, or reads a public property — exactly like Twig.
 *
 * Supported syntax:
 *
 *   Simple comparisons:
 *     entity.status == "pending"        // calls getStatus()
 *     entity.stock > 0                  // calls getStock()
 *     entity.active                     // calls isActive() / getActive()
 *     !entity.archived                  // or: not entity.archived
 *
 *   Combining conditions:
 *     entity.status == "pending" && is_granted("ROLE_EDITOR")
 *     entity.active || entity.status == "draft"
 *
 *   Security:
 *     is_granted("ROLE_ADMIN")
 *     is_granted("ROLE_EDITOR", entity)
 *
 *   Both "entity." and "item." prefixes are supported as variable names.
 *
 * Evaluation is fail-safe: any parse or runtime error returns false (action hidden).
 */
class RowActionExpressionLanguage
{
    private ExpressionLanguage $expressionLanguage;
    private PropertyAccessorInterface $propertyAccessor;

    public function __construct()
    {
        $this->expressionLanguage = new ExpressionLanguage();
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();

        // Register is_granted(attribute, subject = null)
        $this->expressionLanguage->register(
            'is_granted',
            // Compiler (for compiled expressions — not used at runtime, but required by the API)
            static fn (string $attribute, string $subject = 'null'): string => sprintf(
                '($auth !== null && $auth->isGranted(%s, %s))',
                $attribute,
                $subject,
            ),
            // Evaluator
            static function (array $arguments, string $attribute, mixed $subject = null): bool {
                /** @var AuthorizationCheckerInterface|null $auth */
                $auth = $arguments['auth'] ?? null;

                if ($auth === null) {
                    return false;
                }

                // Unwrap proxy if passed as subject so voters receive the real entity
                if ($subject instanceof PropertyAccessProxy) {
                    $subject = $subject->getEntity();
                }

                return $auth->isGranted($attribute, $subject);
            },
        );
    }

    /**
     * Evaluate an expression against an entity row.
     *
     * The entity is wrapped in a PropertyAccess proxy so that `entity.status`
     * automatically resolves to `getStatus()`, `isStatus()`, or a public property —
     * matching the behavior documented and expected by users.
     *
     * Returns false on any error (parse failure, missing property, etc.) as a safe default
     * — a misconfigured expression silently hides the action rather than throwing.
     *
     * @param object                             $entity      The entity row being evaluated
     * @param AuthorizationCheckerInterface|null $authChecker Required only if the expression uses is_granted()
     */
    public function evaluate(
        string $expression,
        object $entity,
        ?AuthorizationCheckerInterface $authChecker = null,
    ): bool {
        $proxy = new PropertyAccessProxy($entity, $this->propertyAccessor);

        try {
            return (bool) $this->expressionLanguage->evaluate(
                $expression,
                [
                    'entity' => $proxy,
                    'item'   => $proxy, // alias for convenience
                    'auth'   => $authChecker,
                ],
            );
        } catch (\Exception) {
            // Safe default: hide the action if expression cannot be evaluated
            return false;
        }
    }
}
