<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Attribute;

use Attribute;

/**
 * Per-property configuration for admin column behaviour.
 *
 * The `editable` parameter controls whether this column can be edited
 * via inline editing. It accepts:
 *
 *   - `true`  (default) — column is editable (subject to voter + writable checks)
 *   - `false` — column is never editable, regardless of permissions
 *   - An expression string evaluated against the entity row using Symfony's
 *     ExpressionLanguage. Supports the same syntax as #[AdminAction(condition: ...)]:
 *
 *       entity.status != "locked"
 *       entity.active && is_granted("ROLE_EDITOR")
 *       is_granted("ROLE_HR")
 *
 * When a string expression is provided, it is evaluated *before* the standard
 * ADMIN_EDIT voter and property-writable checks. All three must pass for the
 * field to be editable.
 *
 * @example Permanently read-only (computed / derived field):
 *   #[AdminColumn(editable: false)]
 *   private float $margin;
 *
 * @example Editable only while the record is unlocked:
 *   #[AdminColumn(editable: 'entity.status != "locked"')]
 *   private string $description;
 *
 * @example Role-gated edit with entity-state condition:
 *   #[AdminColumn(editable: 'entity.isDraft() && is_granted("ROLE_EDITOR")')]
 *   private string $content;
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class AdminColumn
{
    /**
     * @param string|bool $editable
     *   - true  = editable (default, subject to voter + writable checks)
     *   - false = never editable
     *   - string = ExpressionLanguage expression evaluated against the entity
     */
    public function __construct(
        public readonly string|bool $editable = true,
    ) {}
}
