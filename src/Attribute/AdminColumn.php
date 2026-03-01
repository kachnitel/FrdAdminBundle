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
 *   - `null`  (default) — inherit the entity-level setting from
 *             `#[Admin(enableInlineEdit: ...)]`. If the entity has
 *             `enableInlineEdit: true`, the column is editable (subject
 *             to voter + writable checks). If `enableInlineEdit: false`,
 *             the column is read-only.
 *   - `true`  — column is always editable regardless of the entity default
 *             (subject to voter + writable checks). Use to opt a single column
 *             *in* when the entity default is disabled.
 *   - `false` — column is never editable, regardless of entity default or
 *             permissions. The ✎ trigger is hidden entirely.
 *   - An expression string evaluated against the entity row using Symfony's
 *     ExpressionLanguage. The result overrides the entity default entirely:
 *
 *       entity.status != "locked"
 *       entity.active && is_granted("ROLE_EDITOR")
 *       is_granted("ROLE_HR")
 *
 * When a string expression is provided, the entity-level `enableInlineEdit`
 * setting is ignored — the expression takes full control. The standard
 * ADMIN_EDIT voter and property-writable checks still apply when the
 * expression returns true.
 *
 * ## Precedence (checked in order)
 *
 *   1. `editable: false`        → never editable (short-circuits everything)
 *   2. `editable: 'expression'` → evaluate; if false, not editable (entity default bypassed)
 *   3. `editable: true`         → editable (entity default bypassed; still needs voter + writable)
 *   4. `editable: null`         → use entity's `#[Admin(enableInlineEdit: ...)]`
 *
 * @example Opt a column in when the entity default is disabled:
 *   #[AdminColumn(editable: true)]
 *   private string $description;
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
     * @param string|bool|null $editable
     *   - null   = inherit from #[Admin(enableInlineEdit: ...)] (default)
     *   - true   = always editable (overrides entity default; still needs voter + writable)
     *   - false  = never editable (overrides entity default)
     *   - string = ExpressionLanguage expression (overrides entity default; true still needs voter + writable)
     */
    public function __construct(
        public readonly string|bool|null $editable = null,
    ) {}
}
