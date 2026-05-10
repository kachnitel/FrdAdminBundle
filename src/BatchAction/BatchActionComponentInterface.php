<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\BatchAction;

/**
 * Marker interface for LiveComponents that act as batch action handlers.
 *
 * When a BatchAction has $liveComponent set, the _BatchActionButton.html.twig
 * partial renders {{ component(action.liveComponent, {...}) }} instead of
 * a plain form button.
 *
 * The following props are passed by the template to every batch action component:
 *
 *   selectedIds    array<int|string>  — IDs of currently selected entities
 *   entityClass    string             — Fully-qualified entity class name
 *   entityShortClass string           — Short entity class name (e.g. 'Product')
 *
 * Components must declare these as public LiveProps (via BatchActionTrait).
 * After completing their action they must emit 'admin:action:completed' up to EntityList.
 *
 * @see BatchActionTrait Provides the shared LiveProps and completeAction() helper
 * @see DeleteButton     Reference implementation
 * @see ArchiveButton    Reference implementation
 */
interface BatchActionComponentInterface {}
