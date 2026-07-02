<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Form\DynamicEntityFormType;
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
 * Composes AdminFormComponentTrait directly — deliberately NOT by extending
 * AdminEntityForm. See AdminFormComponentTrait's docblock for the full
 * rationale: this class previously extended AdminEntityForm, and the Save &
 * Close button in this component's own template intermittently fired no
 * backend request at all when clicked. That symptom is consistent with a
 * known upstream Symfony UX Live Component reflection gap affecting one
 * #[AsLiveComponent] class extending another. Composing the shared trait
 * instead removes that parent/child LiveComponent relationship entirely.
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
 */
#[AsLiveComponent(
    name: 'K:Admin:EntityType:InlineForm',
    template: '@KachnitelAdmin/components/InlineEntityForm.html.twig',
)]
class InlineEntityForm extends AbstractController
{
    use AdminFormComponentTrait;

    public function __construct(
        protected readonly EntityManagerInterface $em,
        private readonly FormFactoryInterface $formFactory,
    ) {}

    /**
     * Build the form with a unique name to prevent HTML id conflicts.
     *
     * Always creates a new entity — there is no entityId prop on this
     * component; inline add is creation-only. DynamicEntityFormType receives
     * is_root: true so ManyToMany multi-selects are included.
     *
     * @return FormInterface<object|null>
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

        $this->em->persist($entity);
        $this->em->flush();

        $idValues = $this->em
            ->getClassMetadata($entity::class)
            ->getIdentifierValues($entity);

        $rawId    = reset($idValues);
        $entityId = $rawId !== false ? (int) $rawId : 0;

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
     * ReflectionMethod::invoke() is used instead of dynamic method calls
     * ($entity->$method()) to satisfy PHPStan level 8 without a suppression
     * comment — invoke() returns mixed, which is assignable to any type check.
     */
    private function resolveEntityLabel(object $entity): string
    {
        foreach (['getLabel', 'getName', 'getTitle'] as $methodName) {
            if (!method_exists($entity, $methodName)) {
                continue;
            }
            try {
                $result = (new \ReflectionMethod($entity, $methodName))->invoke($entity);
                if (is_string($result) && $result !== '') {
                    return $result;
                }
            } catch (\Throwable) {
                // Method not accessible or threw; try next.
            }
        }

        if (method_exists($entity, '__toString')) {
            try {
                $label = (string) $entity;
                if ($label !== '') {
                    return $label;
                }
            } catch (\Throwable) {
                // Ignore __toString() errors.
            }
        }

        $idValues = $this->em->getClassMetadata($entity::class)->getIdentifierValues($entity);
        $id       = reset($idValues);

        return '#' . ($id !== false ? (string) $id : '?');
    }
}
