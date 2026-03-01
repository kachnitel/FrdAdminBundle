<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\ValueObject\RowAction;

/**
 * Replaces the default page-navigation edit action with an inline-edit component.
 *
 * This provider only supports entities that have `#[Admin(enableInlineEdit: true)]`.
 * For all other entities, `supports()` returns false and the default row action
 * provider's page-navigation Edit link is preserved.
 *
 * Priority 15 sits between DefaultRowActionProvider (0) and AttributeRowActionProvider (50),
 * so the merge order is: Default → InlineEdit (adds liveComponent) → Attribute (user overrides).
 *
 * The RowAction produced here intentionally omits priority (DEFAULT_PRIORITY = "unset")
 * so that RowAction::merge() preserves DefaultRowActionProvider's explicit priority of 20.
 *
 * voterAttribute: ADMIN_EDIT is set so RowActionRuntime hides the button for users
 * without edit permission via a direct voter check.
 */
class InlineEditRowActionProvider implements RowActionProviderInterface
{
    public function __construct(
        private readonly AttributeHelper $attributeHelper,
    ) {}

    /**
     * Only support entities that have explicitly opted into inline editing.
     *
     * @param class-string $entityClass
     */
    public function supports(string $entityClass): bool
    {
        if (!class_exists($entityClass)) {
            return false;
        }

        /** @var Admin|null $admin */
        $admin = $this->attributeHelper->getAttribute($entityClass, Admin::class);

        return $admin !== null && $admin->isEnableInlineEdit();
    }

    /**
     * @return array<RowAction>
     */
    public function getActions(string $entityClass): array
    {
        return [
            new RowAction(
                name: 'edit',
                label: 'Edit',
                icon: '✏️',
                voterAttribute: AdminEntityVoter::ADMIN_EDIT,
                liveComponent: 'K:Admin:RowAction:InlineEdit',
                // priority intentionally omitted — merge() keeps DefaultRowActionProvider's 20
            ),
        ];
    }

    public function getPriority(): int
    {
        return 15;
    }
}
