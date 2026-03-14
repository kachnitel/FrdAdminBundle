<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

use Kachnitel\EntityExpressionLanguage\EntityExpressionLanguage;

/**
 * Evaluates row-action visibility and inline-edit editability expressions.
 *
 * Named subclass of {@see EntityExpressionLanguage} for bundle-specific DI injection.
 *
 * ## Supported syntax
 *
 *   entity.status == "pending"
 *   entity.stock > 0 && is_granted("ROLE_EDITOR")
 *   item.active                       // "item" is an alias for "entity"
 *   is_granted("ADMIN_EDIT", entity)  // passes unwrapped entity to voter
 *
 * Returns `false` on any parse or runtime error.
 */
class RowActionExpressionLanguage extends EntityExpressionLanguage {}
