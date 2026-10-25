<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Editability;

use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\DynamicFormBundle\Editability\FieldEditabilityResolverInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Admin-bundle's implementation of kachnitel/dynamic-form-bundle's
 * FieldEditabilityResolverInterface.
 *
 * Combines #[ColumnPermission] and #[AdminColumn(editable: ...)] to decide which
 * fields DynamicEntityFormType shows on the auto-generated New/Edit form, and
 * which of those are read-only. Bound to the interface via a
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
 * ## Form field state: no entry / read-only / read-write
 *
 * Permissions decide first, and #[AdminColumn(editable: ...)] can only restrict
 * further — never permit:
 *
 *   - ADMIN_SHOW restricted and not granted                       → no entry
 *   - ADMIN_EDIT restricted and not granted                       → read-only
 *   - editable: false                                             → read-only
 *   - editable: 'expression' evaluating false (entity available)  → read-only
 *   - editable: true / null / no attribute                        → no effect
 *   - otherwise                                                   → read-write
 *
 * Read-only fields are rendered disabled (see isReadOnly()), so submitted values
 * are ignored. canEdit() therefore only answers the ADMIN_SHOW question.
 *
 * Note this differs from inline editing in the list view, where editable: false
 * hides the ✎ trigger and the form is not involved.
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
     * Whether the field appears on the form at all: only #[ColumnPermission]
     * ADMIN_SHOW decides. #[AdminColumn(editable: ...)] never hides a field —
     * it can only make it read-only, see isReadOnly().
     *
     * @param class-string $entityClass
     */
    public function canEdit(string $entityClass, string $property, ?object $entity = null): bool
    {
        return $this->isPermitted($entityClass, $property, AdminEntityVoter::ADMIN_SHOW);
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

        if ($column->editable === true) {
            return true;
        }

        if (is_string($column->editable) && $entity !== null) {
            return $this->expressionLanguage->evaluate($column->editable, $entity, $this->authorizationChecker);
        }

        return false;
    }

    /**
     * Read-only when any of these restricts the field (they can only restrict, never permit):
     *
     *   1. #[ColumnPermission] ADMIN_EDIT is restricted and not granted
     *   2. #[AdminColumn(editable: false)]
     *   3. #[AdminColumn(editable: 'expression')] evaluates false — only decidable
     *      once an $entity is available; until then the field is editable and
     *      DynamicFormEditabilityListener asks again after the entity is bound.
     *
     * @param class-string $entityClass
     */
    public function isReadOnly(string $entityClass, string $property, ?object $entity = null): bool
    {
        if (!$this->isPermitted($entityClass, $property, AdminEntityVoter::ADMIN_EDIT)) {
            return true;
        }

        $editable = $this->getAdminColumn($entityClass, $property)?->editable;

        if ($editable === false) {
            return true;
        }

        if (is_string($editable) && $entity !== null) {
            return !$this->expressionLanguage->evaluate($editable, $entity, $this->authorizationChecker);
        }

        return false;
    }

    /**
     * #[ColumnPermission] check. A class that can't be reflected has no
     * restrictions to apply — same fallback as getAdminColumn().
     *
     * @param class-string $entityClass
     */
    private function isPermitted(string $entityClass, string $property, string $action): bool
    {
        try {
            return $this->columnPermissions->canPerformAction($entityClass, $property, $action);
        } catch (\ReflectionException) {
            return true;
        }
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
