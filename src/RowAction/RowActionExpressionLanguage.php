<?php
// src/RowAction/RowActionExpressionLanguage.php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;

/**
 * Evaluates admin expressions (row-action conditions, `editable:` expressions,
 * archive expressions) against an entity.
 *
 * Expressions use Symfony's native evaluator: call getters explicitly
 * (`entity.getStatus()`); property-style access to private properties
 * (`entity.status`) is not supported.
 *
 * Failure policy mirrors RowActionVisibilityChecker's DI conditions:
 *   - kernel.debug: a RuntimeException naming the expression and entity is thrown
 *     so misconfiguration surfaces immediately instead of a silently missing button.
 *   - otherwise: a warning is logged and the expression evaluates to false.
 */
class RowActionExpressionLanguage
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly ExpressionLanguage $expressionLanguage,
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug = false,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @throws \RuntimeException in debug mode when the expression is invalid or fails to evaluate
     */
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
        } catch (\Throwable $e) {
            if ($this->debug) {
                throw new \RuntimeException(
                    sprintf(
                        'Expression "%s" failed for entity %s: %s',
                        $expression,
                        $entity::class,
                        $e->getMessage(),
                    ),
                    previous: $e,
                );
            }

            $this->logger->warning(
                'Expression failed — treated as false (action hidden / not editable / not archived).',
                [
                    'expression' => $expression,
                    'entity'     => $entity::class,
                    'exception'  => $e->getMessage(),
                ],
            );

            return false;
        }
    }
}
