<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

/**
 * Marker interface for Twig/Live Components usable as RowAction components.
 *
 * When a RowAction has $liveComponent set, the _RowActionButton.html.twig partial
 * renders {{ component(action.liveComponent, {...}) }} instead of a button or link.
 * The following props are always passed by the template:
 *
 *   entity           object  — the entity row being rendered
 *   entityShortClass string  — short class name used for voter checks
 *   isRowEditing     bool    — whether this specific row is currently in inline-edit mode
 *
 * Components implementing this interface must expose these as public properties
 * (the TwigComponent prop convention) so the Twig {{ component() }} call can
 * inject them.
 *
 * Permission checks are the component's responsibility. The RowAction voter
 * attribute check in RowActionRuntime bypasses the route/form existence requirement
 * for component actions and checks the voter directly via AuthorizationChecker,
 * so the action is still hidden from the action list when the voter denies access.
 *
 * Example:
 *
 *   #[AsTwigComponent('K:Admin:RowAction:MyButton')]
 *   class MyButton implements RowActionComponentInterface
 *   {
 *       public object $entity;
 *       public string $entityShortClass = '';
 *       public bool $isRowEditing = false;
 *   }
 *
 *   // Register via AdminAction attribute on the entity:
 *   #[AdminAction(name: 'my_action', label: 'My Action', liveComponent: 'K:Admin:RowAction:MyButton')]
 *
 *   // Or programmatically in a RowActionProviderInterface implementation:
 *   new RowAction(name: 'my_action', label: 'My Action', liveComponent: 'K:Admin:RowAction:MyButton')
 */
interface RowActionComponentInterface {}
