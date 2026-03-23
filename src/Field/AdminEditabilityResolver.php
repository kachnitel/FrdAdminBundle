<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Field;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
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
 * After the above resolves to eligible, two more gates apply:
 *   5. ADMIN_EDIT voter (Symfony security)
 *   6. PropertyAccessor::isWritable() (setter presence)
 */
final class AdminEditabilityResolver implements EditabilityResolverInterface
{
    public function __construct(
        private readonly AttributeHelper $attributeHelper,
        private readonly RowActionExpressionLanguage $expressionLanguage,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly PropertyAccessorInterface $propertyAccessor,
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
