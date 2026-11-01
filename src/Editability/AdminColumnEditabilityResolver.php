<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Editability;

use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\DynamicFormBundle\Editability\FieldEditabilityResolverInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Admin-bundle's implementation of kachnitel/dynamic-form-bundle's
 * FieldEditabilityResolverInterface.
 *
 * Reads #[AdminColumn(editable: ...)] to decide which fields DynamicEntityFormType
 * includes on the auto-generated New/Edit form. Bound to the interface via a
 * services.yaml alias, exactly as AdminEditabilityResolver (this class's sibling,
 * below) is bound to kachnitel/entity-components-bundle's own
 * EditabilityResolverInterface for the list-view inline-edit feature.
 *
 * ## This is deliberately NOT the same precedence as AdminEditabilityResolver
 *
 * AdminEditabilityResolver falls back to #[Admin(enableInlineEdit: ...)] when a
 * property has no #[AdminColumn] override — inline editing is an opt-in feature,
 * off by default. Form generation is not opt-in: a property with no #[AdminColumn]
 * attribute at all must still appear on the New/Edit form. enableInlineEdit only
 * concerns the list view's pencil-icon editor and has no bearing on the form page.
 * If this resolver ever started consulting it, every entity without
 * enableInlineEdit: true would lose every field from its auto-generated form —
 * this class must never read #[Admin] at all.
 *
 * ## Visibility vs editability
 *
 * Only #[ColumnPermission] ADMIN_SHOW can remove a field; #[AdminColumn(editable)] never does.
 * It only decides whether a visible field can be changed (see isReadOnly()):
 *
 *   1. No #[AdminColumn] attribute  → editable (permissive default)
 *   2. editable: false              → read-only (rendered disabled)
 *   3. editable: 'expression'       → read-only when it evaluates to false
 *   4. editable: true               → editable
 *
 * ## Column permissions (#[ColumnPermission])
 *
 * Combined with the above (either one makes a visible field read-only):
 *
 *   ADMIN_SHOW denied                 → canEdit() false, isExplicitOverride() false
 *                                        (field is not on the form at all)
 *   ADMIN_SHOW ok, ADMIN_EDIT denied  → canEdit() true, isReadOnly() true
 *                                        (rendered disabled; submitted data is ignored)
 *
 * ## isExplicitOverride() precedence (checked in order)
 *
 * Consulted only to decide whether a structurally auto-skipped association
 * (inverse side / parent back-reference) should be added back into the form —
 * see FieldEditabilityResolverInterface's docblock. Must NOT fall back to any
 * entity-level default, unlike canEdit().
 *
 *   1. No #[AdminColumn] attribute  → not overridden
 *   2. editable: false              → not overridden
 *   3. editable: true               → overridden (bypasses the auto-skip)
 *   4. editable: 'expression'       → evaluate if $entity is available; if not,
 *                                      not overridden (yet)
 *
 * @see \Kachnitel\AdminBundle\Field\AdminEditabilityResolver the sibling resolver
 *      for list-view inline editing — same underlying attribute, different defaults
 */
final class AdminColumnEditabilityResolver implements FieldEditabilityResolverInterface
{
    public function __construct(
        private readonly AttributeHelper $attributeHelper,
        private readonly RowActionExpressionLanguage $expressionLanguage,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly ColumnPermissionService $columnPermissions,
    ) {}

    /**
     * @param class-string $entityClass
     */
    public function canEdit(string $entityClass, string $property, ?object $entity = null): bool
    {
        // Visibility is decided by #[ColumnPermission] ADMIN_SHOW alone. #[AdminColumn(editable)]
        // only decides whether a visible field can be changed, see isReadOnly().
        return $this->columnPermissions->canPerformAction($entityClass, $property, AdminEntityVoter::ADMIN_SHOW);
    }

    /**
     * @param class-string $entityClass
     */
    public function isExplicitOverride(string $entityClass, string $property, ?object $entity = null): bool
    {
        $column = $this->getAdminColumn($entityClass, $property);

        if ($column === null) {
            return false;
        }

        if (!$this->columnPermissions->canPerformAction($entityClass, $property, AdminEntityVoter::ADMIN_SHOW)) {
            return false;
        }

        if ($column->editable === true) {
            return true;
        }

        if (is_string($column->editable) && $entity !== null) {
            return $this->expressionLanguage->evaluate($column->editable, $entity, $this->authorizationChecker);
        }

        return false;
    }

    /**
     * Show the value but don't let it change: ADMIN_EDIT denied, `editable: false`, or an
     * `editable: 'expression'` that evaluates to false for the bound entity.
     *
     * Only consulted by DynamicEntityFormType for fields canEdit() accepted. With no
     * entity yet an expression can't be evaluated, so the field is built editable and
     * DynamicFormEditabilityListener drops it once a real entity is bound if the
     * expression turns out false (it can't disable an already-built field).
     *
     * @param class-string $entityClass
     */
    public function isReadOnly(string $entityClass, string $property, ?object $entity = null): bool
    {
        if (!$this->columnPermissions->canPerformAction($entityClass, $property, AdminEntityVoter::ADMIN_EDIT)) {
            return true;
        }

        $editable = $this->getAdminColumn($entityClass, $property)?->editable;

        if (is_string($editable)) {
            return $entity !== null && !$this->expressionLanguage->evaluate($editable, $entity, $this->authorizationChecker);
        }

        return $editable === false;
    }

    /**
     * @param class-string $entityClass
     * @noinspection PhpUnhandledExceptionInspection
     */
    private function getAdminColumn(string $entityClass, string $property): ?AdminColumn
    {
        try {
            /** @var AdminColumn|null $attr */
            $attr = $this->attributeHelper->getPropertyAttribute($entityClass, $property, AdminColumn::class);

            return $attr;
        } catch (\Throwable) {
            // Property doesn't exist, class doesn't resolve, or any other
            // reflection failure — treat as "no attribute", matching
            // DynamicEntityFormType's original inline-reflection behaviour.
            return null;
        }
    }
}
