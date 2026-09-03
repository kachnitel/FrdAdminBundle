<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Field;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\EntityComponentsBundle\Components\Field\EditabilityResolverInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Admin-bundle implementation of EditabilityResolverInterface.
 *
 * Enforces editability policy based on:
 *   1. #[AdminColumn(editable: ...)] attribute on the property (explicit opt-in/opt-out)
 *   2. #[Admin(enableInlineEdit: ...)] attribute on the entity class (default)
 *   3. Symfony ADMIN_EDIT voter for the entity type
 *   4. PropertyAccessor writability check (setter must exist)
 *
 * ## Precedence (checked in order)
 *
 *   1. `editable: false`        → never editable (short-circuits everything)
 *   2. `editable: 'expression'` → evaluate expression; entity default bypassed
 *   3. `editable: true`         → editable (entity default bypassed; still needs voter + writable)
 *   4. `editable: null`         → read entity's `#[Admin(enableInlineEdit: ...)]`
 *
 * After the above resolves to eligible, three more gates apply:
 *   5. ADMIN_EDIT voter (Symfony security) — class-level, keyed on the entity's short name
 *   5b. Object-level authorization via ObjectAuthorizationChecker — this specific entity
 *       instance. No-op unless the entity's class has #[Admin(enableObjectAuth: true)].
 *   6. PropertyAccessor::isWritable() (setter presence)
 *
 * ## This is the single choke point for inline-edit authorization
 *
 * entity-components-bundle's AbstractEditableField calls canEdit() in exactly two
 * places: to decide whether to render the ✎ trigger, and — the security-relevant
 * call — inside save(), before persistEdit() writes anything. Both go through this
 * method, so this is the only place inline-edit needs object-level authorization.
 * EntityList::editRow() (the row-level "enter edit mode" action) also checks it,
 * but only as a UX convenience — each field component enforces independently of
 * $editingRowId, so that check has no bearing on security by itself.
 *
 * ## Known limitation: checked before the write, not after
 *
 * AbstractEditableField::save() calls canEdit() against the entity's *current*
 * state, then writes the new value only if that passes — there is no second
 * check against the post-write state. If the property being edited is the same
 * one an application voter's decision reads, the check validates the old value,
 * not the new one. This differs from the New/Edit form, where
 * ObjectAuthorizedFormInterface checks run after the whole submission is bound.
 * It cannot be fixed from this class — the check/write ordering is fixed inside
 * entity-components-bundle's AbstractEditableField::save(), a separate package.
 * See docs/OBJECT_AUTHORIZATION.md#inline-editing for the full explanation.
 */
final class AdminEditabilityResolver implements EditabilityResolverInterface
{
    public function __construct(
        private readonly AttributeHelper $attributeHelper,
        private readonly RowActionExpressionLanguage $expressionLanguage,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly ObjectAuthorizationChecker $objectAuthChecker,
    ) {}

    public function canEdit(object $entity, string $property): bool
    {
        /** @var AdminColumn|null $attr */
        $attr = $this->attributeHelper->getPropertyAttribute(
            $entity::class,
            $property,
            AdminColumn::class,
        );

        if (!$this->isEligibleByAttribute($entity, $attr)) {
            return false;
        }

        // 5. Voter check — AdminEntityVoter must grant ADMIN_EDIT for this entity type
        $shortClass = (new \ReflectionClass($entity))->getShortName();
        if (!$this->authorizationChecker->isGranted('ADMIN_EDIT', $shortClass)) {
            return false;
        }

        // 5b. Object-level authorization — this specific entity instance. No-op
        // when the entity's class doesn't have #[Admin(enableObjectAuth: true)].
        if (!$this->objectAuthChecker->isGranted(AdminEntityVoter::ADMIN_EDIT, $entity)) {
            return false;
        }

        // 6. Property must have a setter
        return $this->propertyAccessor->isWritable($entity, $property);
    }

    /**
     * Determine editability eligibility based solely on #[AdminColumn] and #[Admin] attributes.
     *
     * Returns false when the column/entity configuration prohibits editing.
     * Returns true when it is permitted (voter + writable checks still apply after).
     */
    private function isEligibleByAttribute(object $entity, ?AdminColumn $attr): bool
    {
        // 1. Explicit false — never editable; short-circuits everything
        if ($attr !== null && $attr->editable === false) {
            return false;
        }

        // 2. Expression string — evaluate; entity default bypassed entirely
        if ($attr !== null && is_string($attr->editable)) {
            return $this->expressionLanguage->evaluate(
                $attr->editable,
                $entity,
                $this->authorizationChecker,
            );
        }

        // 3. Explicit true — bypass entity default; eligible
        if ($attr !== null && $attr->editable === true) {
            return true;
        }

        // 4. null or no attribute — check entity-level enableInlineEdit
        /** @var Admin|null $adminAttr */
        $adminAttr = $this->attributeHelper->getAttribute($entity::class, Admin::class);

        return $adminAttr !== null && $adminAttr->isEnableInlineEdit();
    }
}
