<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;

class RowActionExpressionLanguage
{
    public function __construct(
        private readonly ExpressionLanguage $expressionLanguage,
    ) {}

    public function evaluate(
        string $expression,
        object $entity,
        ?AuthorizationCheckerInterface $authChecker = null,
    ): bool {
        try {
            return (bool) $this->expressionLanguage->evaluate($expression, [
                'entity'       => $entity,
                'item'         => $entity,      // alias
                'auth_checker' => $authChecker, // used by the built-in is_granted()
            ]);
        } catch (\Throwable) {
            return false;
        }
    }
}
