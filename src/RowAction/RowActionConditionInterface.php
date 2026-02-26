<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Marker interface for services used as DI tuple conditions in #[AdminAction].
 *
 * Implementing this interface registers the service in the row action condition
 * locator, making it resolvable at render time without injecting the full container.
 *
 * The implementing class must expose a public method that accepts the entity object
 * and returns bool. The method name is specified in the condition tuple:
 *
 *   #[AdminAction(
 *       name: 'refund',
 *       label: 'Refund',
 *       condition: [RefundService::class, 'canRefund'],
 *   )]
 *
 *   class RefundService implements RowActionConditionInterface
 *   {
 *       public function __construct(
 *           private readonly OrderRepository $orders,
 *       ) {}
 *
 *       public function canRefund(object $entity): bool
 *       {
 *           return $this->orders->isEligibleForRefund($entity);
 *       }
 *   }
 *
 * No methods are required by this interface — it is a marker for service discovery.
 */
#[AutoconfigureTag]
interface RowActionConditionInterface {}
