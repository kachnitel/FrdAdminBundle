<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\RowAction;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\ValueObject\RowAction;

/**
 * Replaces the default page-navigation edit action with an inline-edit component,
 * but only in the index (list) context.
 *
 * This provider only activates for entities with #[Admin(enableInlineEdit: true)].
 * For all other entities, supports() returns false and the Default edit link is preserved.
 *
 * contexts: [RowAction::CONTEXT_INDEX] — InlineEditButton fires `editRow` on the parent
 * EntityList LiveComponent via a Stimulus data-action attribute. That parent does not exist
 * on show/edit page headers. By restricting to CONTEXT_INDEX, RowActionRegistry skips this
 * action entirely when resolving for show/edit, leaving the DefaultRowActionProvider's
 * plain-link edit action untouched and visible there.
 *
 * Priority 15 sits between DefaultRowActionProvider (0) and AttributeRowActionProvider (50).
 * priority is omitted (DEFAULT_PRIORITY) so merge() preserves DefaultRowActionProvider's explicit 20.
 */
class InlineEditRowActionProvider implements RowActionProviderInterface
{
    public function __construct(
        private readonly AttributeHelper $attributeHelper,
    ) {}

    /**
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
                contexts: [RowAction::CONTEXT_INDEX],
                // priority intentionally omitted — merge() keeps DefaultRowActionProvider's 20
            ),
        ];
    }

    public function getPriority(): int
    {
        return 15;
    }
}
