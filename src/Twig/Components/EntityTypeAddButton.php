<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Renders a small "Add new <Entity>" button next to EntityType autocomplete fields.
 *
 * Placed by the admin_compact form theme next to any EntityType widget whose options
 * include `attr: ['data-admin-entity-class' => ...]` (set automatically by
 * DoctrineFormTypeMapper for all association-backed EntityType fields).
 *
 * Renders nothing when the current user lacks ADMIN_NEW permission for the
 * target entity type.
 *
 * When clicked, opens a native HTML <dialog> containing a K:Admin:EntityType:InlineForm
 * LiveComponent. The dialog is managed by the `admin-inline-add` Stimulus controller.
 * After a successful save the controller closes the dialog and auto-selects the new
 * entity in the parent form's Tom Select autocomplete widget.
 *
 * ## Shadcn dialog adaptation
 * The default implementation uses the native HTML <dialog> element. If you have the
 * Symfony UX Toolkit shadcn kit installed you can swap the dialog markup in your
 * template override — see docs/INLINE_ADD.md.
 * @see \Kachnitel\AdminBundle\Tests\Twig\Components\EntityTypeAddButtonTest
 */
#[AsTwigComponent(name: 'K:Admin:EntityType:AddButton', template: '@KachnitelAdmin/components/EntityTypeAddButton.html.twig')]
class EntityTypeAddButton
{
    /**
     * Fully-qualified class name of the entity type to be created inline.
     * Populated by the admin_compact form theme from the field's
     * `attr['data-admin-entity-class']` option.
     */
    public string $targetEntityClass = '';

    /**
     * HTML form field name (form.vars.full_name) of the parent EntityType field.
     * Used by the Stimulus controller to locate the Tom Select instance and
     * auto-select the newly created entity after the dialog closes.
     * Examples: `order[category]`, `order[tags]`.
     */
    public string $fieldName = '';

    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly EntityDiscoveryService $entityDiscovery,
    ) {}

    /**
     * Whether the current user may create a new entity of the target type.
     * Checked against AdminEntityVoter::ADMIN_NEW using the entity's short name.
     */
    #[ExposeInTemplate]
    public function canCreate(): bool
    {
        if ($this->targetEntityClass === '') {
            return false;
        }

        try {
            return $this->authorizationChecker->isGranted(
                AdminEntityVoter::ADMIN_NEW,
                $this->getEntityShortName(),
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Unqualified class name used as the voter subject and dialog title suffix.
     * E.g. 'App\Entity\Category' → 'Category'.
     *
     * Uses basename() on the backslash-to-slash replacement to avoid the
     * string|false return-type ambiguity of end() that PHPStan flags at level 8.
     */
    #[ExposeInTemplate]
    public function getEntityShortName(): string
    {
        return basename(str_replace('\\', '/', $this->targetEntityClass));
    }

    /**
     * Human-readable label for the entity, sourced from #[Admin(label:)] when
     * available, falling back to the short class name.
     */
    #[ExposeInTemplate]
    public function getEntityLabel(): string
    {
        if ($this->targetEntityClass === '') {
            return $this->getEntityShortName();
        }

        /** @var class-string $entityClass */
        $entityClass = $this->targetEntityClass;
        $adminAttr   = $this->entityDiscovery->getAdminAttribute($entityClass);

        return $adminAttr?->getLabel() ?? $this->getEntityShortName();
    }

    /**
     * Form type class to use in the inline creation dialog.
     *
     * Resolution order:
     *   1. Explicit #[Admin(formType:)] on the target entity class
     *   2. DynamicEntityFormType (auto-generates a form from Doctrine metadata)
     *
     * Note: conventionally-named form types (e.g. App\Form\CategoryFormType) that
     * are not referenced via #[Admin(formType:)] are not detected here. If you need
     * a custom form in the inline dialog, set formType on the entity's Admin attribute.
     */
    #[ExposeInTemplate]
    public function getFormTypeClass(): string
    {
        if ($this->targetEntityClass === '') {
            return DynamicEntityFormType::class;
        }

        /** @var class-string $entityClass */
        $entityClass = $this->targetEntityClass;
        $adminAttr   = $this->entityDiscovery->getAdminAttribute($entityClass);
        $formType    = $adminAttr?->getFormType();

        return $formType ?? DynamicEntityFormType::class;
    }
}
