<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;

/**
 * LiveComponent for inline entity creation inside the EntityTypeAddButton dialog.
 *
 * ## Form submission — protected proxies
 *
 * doSubmitForm()/doGetForm() (declared on AdminFormComponentTrait) are used
 * here for a calling convention consistent with AdminEntityForm, even though
 * this class could now call ComponentWithFormTrait's submitForm()/getForm()
 * directly (they're private to this class, not to the trait, following
 * standard PHP trait flattening — but going through the named proxy methods
 * keeps both components' save() implementations reading the same way).
 *
 * ## Form name uniqueness
 *
 * instantiateForm() calls FormFactoryInterface::createNamed() with a name derived
 * from the entity FQCN (e.g. 'inline_app_entity_category') instead of using the
 * block-prefix default. This prevents HTML id attribute collisions when the same
 * entity type appears in both the page form and the inline dialog simultaneously
 * (particularly important for self-referencing relationships).
 *
 * AbstractController::createForm() is not used here because AbstractController
 * has no createNamedForm() shortcut; FormFactoryInterface is injected directly
 * so PHPStan can verify the call at level 8.
 *
 * ## Object-level authorization
 *
 * Implements ObjectAuthorizedFormInterface (ADMIN_NEW — inline creation is
 * always a new entity), so AdminFormComponentTrait::doSubmitForm() enforces
 * object-level authorization (see docs/OBJECT_AUTHORIZATION.md) on the
 * bound entity right after form binding and before persist(), the same way
 * AdminEntityForm's New page does. This is a no-op for entities without
 * #[Admin(enableObjectAuth: true)]. Without it, object-level
 * create authorization enforced on the main New page could be bypassed
 * entirely by going through this dialog instead.
 *
 * ## After-save flow
 *
 * On success, dispatches the 'admin:inline:entity:saved' browser event with:
 *   - entityClass  : FQCN of the newly created entity
 *   - entityId     : integer primary key
 *   - entityLabel  : human-readable display string (resolved via getLabel/getName/…)
 *
 * The admin-inline-add Stimulus controller receives this event, closes the
 * dialog, and auto-selects the new entity in the parent form's Tom Select widget.
 *
 * On validation failure returns without dispatching — the dialog stays open
 * so the user can correct inline validation errors.
 *
 * ## OneToMany associations in the inline dialog
 *
 * addCollectionItem() / removeCollectionItem() (from LiveCollectionTrait, brought
 * in via AdminFormComponentTrait) are now composed directly into this class
 * rather than inherited from a parent component, so the reflection gap that
 * motivated this refactor should no longer apply to them either. This has not
 * yet been covered by a dedicated test, though — until it is, prefer
 * #[AdminColumn(editable: false)] on OneToMany properties of the related entity
 * to exclude them from the inline dialog.
 *
 * ## REVIEW: loading: "lazy" is required for LiveComponent inside a closed <dialog>
 *
 * EntityTypeAddButton.html.twig renders this component inside a closed
 * native <dialog> element and deliberately passes `loading: "lazy"` to
 * component(). This is NOT an optional performance tweak — eagerly
 * mounting a LiveComponent inside a closed <dialog> (dialogs start closed;
 * they only open via showModal() on user interaction) causes the first
 * inline-add dialog rendered on a page to permanently fail: its form area
 * never mounts, and reopening that same dialog later does not recover it.
 * A *second*, different dialog opened afterwards mounts correctly, which
 * is what makes this bug easy to miss in ad-hoc manual testing.
 *
 * Suspected mechanism (not yet root-caused inside the upstream package):
 * a closed <dialog> is removed from the normal document flow, and UX
 * LiveComponent's eager (non-lazy) mount path appears to depend on
 * layout/visibility that isn't available for content inside a closed
 * <dialog>. `loading: "lazy"` defers the mount until the dialog is
 * actually opened, sidestepping the problematic phase entirely.
 *
 * @see \Kachnitel\AdminBundle\Tests\Twig\Components\EntityTypeAddButtonLazyLoadingRegressionTest
 * @see \Kachnitel\AdminBundle\Twig\Components\EntityTypeAddButton the caller that sets loading: "lazy"
 * @see https://github.com/kachnitel/FrdAdminBundle/issues/12 full investigation, repro steps, resolution plan
 * @see ObjectAuthorizedFormInterface
 *
 * @template TData of object|null
 */
#[AsLiveComponent(
    name: 'K:Admin:EntityType:InlineForm',
    template: '@KachnitelAdmin/components/InlineEntityForm.html.twig',
)]
class InlineEntityForm extends AbstractController implements ObjectAuthorizedFormInterface
{
    /** @use AdminFormComponentTrait<TData> */
    use AdminFormComponentTrait;

    public function __construct(
        protected readonly EntityManagerInterface $em,
        private readonly FormFactoryInterface $formFactory,
    ) {}

    /**
     * Always ADMIN_NEW — inline creation is always a new entity, there is
     * no entityId prop on this component to distinguish an edit.
     */
    public function getObjectAuthorizationAttribute(): string
    {
        return AdminEntityVoter::ADMIN_NEW;
    }

    /**
     * Build the form with a unique name to prevent HTML id conflicts.
     *
     * Always creates a new entity — there is no entityId prop on this
     * component; inline add is creation-only. DynamicEntityFormType receives
     * is_root: true so ManyToMany multi-selects are included.
     *
     * @return FormInterface<TData>
     */
    protected function instantiateForm(): FormInterface
    {
        /** @var class-string $entityClass */
        $entityClass = $this->entityClass;

        /** @var class-string<FormTypeInterface<object>> $formTypeClass */
        $formTypeClass = $this->formTypeClass;

        $options = ['csrf_protection' => false];

        if ($formTypeClass === DynamicEntityFormType::class) {
            $options['entity_class'] = $entityClass;
            $options['data_class']   = $entityClass;
            $options['is_root']      = true;
        }

        // Derive a unique, stable form name from the entity FQCN so that two
        // forms for the same entity type (page form + inline dialog) never share
        // the same HTML id prefixes.
        // preg_replace() returns string|null; the fallback ensures a valid name.
        $sanitized = preg_replace('/[^a-z0-9]+/i', '_', $entityClass) ?? $entityClass;
        $formName  = 'inline_' . mb_strtolower($sanitized);

        // null data → always a "new entity" form; there is no entityId to look up.
        /** @var FormInterface<TData> */
        return $this->formFactory->createNamed($formName, $formTypeClass, null, $options);
    }

    /**
     * Persist the new entity and dispatch 'admin:inline:entity:saved'.
     *
     * Success → browser event fired → Stimulus closes dialog + auto-selects value.
     * Failure → returns early; dialog stays open; inline validation errors shown.
     */
    #[LiveAction]
    public function save(): void
    {
        try {
            $this->doSubmitForm();
        } catch (UnprocessableEntityHttpException) {
            // Invalid form — re-render with inline errors, dialog stays open.
            return;
        }

        /** @var object $entity */
        $entity = $this->doGetForm()->getData();

        // Object-level authorization for this save already ran inside the
        // doSubmitForm() call above (AdminFormComponentTrait, via
        // ObjectAuthorizedFormInterface) — see this class's docblock.

        $this->em->persist($entity);
        $this->em->flush();

        $idValues = $this->em
            ->getClassMetadata($entity::class)
            ->getIdentifierValues($entity);

        $rawId    = reset($idValues);
        $entityId = is_int($rawId) || is_numeric($rawId) ? (int) $rawId : 0;

        $this->dispatchBrowserEvent('admin:inline:entity:saved', [
            'entityClass' => $this->entityClass,
            'entityId'    => $entityId,
            'entityLabel' => $this->resolveEntityLabel($entity),
        ]);
    }

    /**
     * Derive a human-readable label for the newly created entity.
     *
     * Mirrors the priority order of the admin_entity_label() Twig function:
     *   getLabel() → getName() → getTitle() → __toString() → #id
     *
     * Split into tryGetterLabel() / tryToStringLabel() / fallbackIdLabel() so
     * each step stays under PHPMD's CyclomaticComplexity threshold; this
     * method itself is now just a null-coalescing pipeline through them.
     */
    private function resolveEntityLabel(object $entity): string
    {
        return $this->tryGetterLabel($entity)
            ?? $this->tryToStringLabel($entity)
            ?? $this->fallbackIdLabel($entity);
    }

    /**
     * Try getLabel() -> getName() -> getTitle(), in that order, returning the
     * first non-empty string result. Returns null when none of the methods
     * exist, none return a non-empty string, or all throw.
     *
     * ReflectionMethod::invoke() is used instead of dynamic method calls
     * ($entity->$method()) to satisfy PHPStan level 8 without a suppression
     * comment — invoke() returns mixed, which is assignable to any type check.
     */
    private function tryGetterLabel(object $entity): ?string
    {
        foreach (['getLabel', 'getName', 'getTitle'] as $methodName) {
            if (!method_exists($entity, $methodName)) {
                continue;
            }

            try {
                $result = (new \ReflectionMethod($entity, $methodName))->invoke($entity);
            } catch (\Throwable) {
                continue; // Method not accessible or threw; try next.
            }

            if (is_string($result) && $result !== '') {
                return $result;
            }
        }

        return null;
    }

    /**
     * Try __toString(), returning it when non-empty. Returns null when the
     * entity has no __toString(), it throws, or it returns an empty string.
     */
    private function tryToStringLabel(object $entity): ?string
    {
        if (!method_exists($entity, '__toString')) {
            return null;
        }

        try {
            $label = (string) $entity;
        } catch (\Throwable) {
            return null; // Ignore __toString() errors.
        }

        return $label !== '' ? $label : null;
    }

    /**
     * Last-resort label: the entity's identifier prefixed with '#', or '#?'
     * when no identifier value is available.
     */
    private function fallbackIdLabel(object $entity): string
    {
        $idValues = $this->em->getClassMetadata($entity::class)->getIdentifierValues($entity);
        $id       = reset($idValues);

        return '#' . (is_int($id) || is_string($id) ? $id : '?');
    }
}
