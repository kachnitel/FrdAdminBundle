<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Form\DynamicEntityFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Generic live form component for admin edit and new entity pages.
 *
 * Uses LiveCollectionTrait (which itself uses ComponentWithFormTrait) to provide
 * addCollectionItem() and removeCollectionItem() LiveActions required by
 * LiveCollectionType when DynamicEntityFormType renders OneToMany collections.
 *
 * When `formTypeClass` is `DynamicEntityFormType::class`, the component
 * automatically passes the required `entity_class` and `is_root: true` options
 * so that collection associations are included in the top-level form.
 *
 * Coordinates with the K:Admin:Action:Save button (rendered separately in the
 * page header, not a child of this component) purely through the LiveComponent
 * broadcast event system — see broadcastFormState().
 *
 * @see \Kachnitel\AdminBundle\Controller\AbstractAdminController
 * @see docs/DYNAMIC_FORM_COLLECTIONS.md
 */
#[AsLiveComponent(name: 'K:Admin:EntityForm', template: '@KachnitelAdmin/components/AdminEntityForm.html.twig')]
class AdminEntityForm extends AbstractController
{
    use DefaultActionTrait;
    use LiveCollectionTrait {
        LiveCollectionTrait::getFormView as private getFormViewFromTrait;
    }
    use ComponentToolsTrait;

    /**
     * Fully-qualified entity class name (e.g. App\Entity\Product).
     */
    #[LiveProp]
    public string $entityClass = '';

    /**
     * Entity primary key. Null for new entities.
     */
    #[LiveProp]
    public ?int $entityId = null;

    /**
     * Fully-qualified form type class name.
     * May be a hand-written FormType or DynamicEntityFormType::class.
     */
    #[LiveProp]
    public string $formTypeClass = '';

    public function __construct(protected readonly EntityManagerInterface $em) {}

    /**
     * REVIEW: only here because private method in trait with #[ExposeInTemplate] doesn't propagate to
     * components extending this AdminEntityForm
     * Expose getFormView() to Twig templates.
     *
     * Re-exposed here because #[ExposeInTemplate] on a private aliased method
     * in a trait does not propagate automatically to the component class.
     */
    #[ExposeInTemplate('form')]
    public function getFormView(): FormView
    {
        return $this->getFormViewFromTrait();
    }

    /**
     * Build the Symfony form bound to the entity.
     *
     * When formTypeClass is DynamicEntityFormType, the required `entity_class` option
     * is added automatically — the caller does not need to supply it. `is_root: true`
     * is passed explicitly so that DynamicEntityFormType includes collection associations
     * (ManyToMany multi-selects and OneToMany LiveCollectionType fields).
     *
     * CSRF protection is disabled at the form level — LiveComponent handles
     * its own request-level CSRF separately.
     *
     * @return FormInterface<object|null>
     */
    protected function instantiateForm(): FormInterface
    {
        /** @var class-string $entityClassName */
        $entityClassName = $this->entityClass;

        $entity = $this->entityId !== null
            ? $this->em->find($entityClassName, $this->entityId)
            : null;

        /** @var class-string<FormTypeInterface<object>> $formTypeClass */
        $formTypeClass = $this->formTypeClass;

        $options = ['csrf_protection' => false];

        if ($formTypeClass === DynamicEntityFormType::class) {
            $options['entity_class'] = $entityClassName;
            // data_class must be passed explicitly — DynamicEntityFormType cannot derive
            // it from entity_class via a lazy closure because Symfony validates data_class
            // against allowedTypes(['null', 'string']) before OptionsResolver fires closures.
            $options['data_class'] = $entityClassName;
            // is_root: true ensures collection associations (ManyToMany, OneToMany) are
            // included at the top level. Child forms created by LiveCollectionType receive
            // is_root: false via entry_options, preventing infinite recursion in
            // bidirectional relationships.
            $options['is_root'] = true;
            // entity_instance: pass the actual entity so expressions in #[AdminColumn(editable: ...)]
            // can be evaluated during form building. For existing entities, this is the loaded instance.
            // For new entities, this is a fresh instance. Enables expression-based editability rules.
            $options['entity_instance'] = $entity;
        }

        return $this->createForm($formTypeClass, $entity, $options);
    }

    /**
     * Broadcasts the form's current validity to any listening
     * K:Admin:Action:Save button, purely for a non-blocking visual hint (see
     * SaveButton's docblock for why this doesn't gate its disabled state).
     *
     * K:Admin:Action:Save is a sibling, not a child (rendered in the page
     * header block vs. this component's content block — see admin/edit.html.twig,
     * admin/new.html.twig), so LiveProp parent/child binding isn't available;
     * broadcast events are the only channel between the two.
     */
    public function broadcastFormState(): void
    {
        $this->emit(
            'admin:form:state',
            [ 'valid' => $this->isFormValid() ? 1 : 0 ],
            'K:Admin:Action:Save'
        );
    }

    /**
     * Whether the form is currently valid. True for an untouched form (not
     * yet submitted), since FormInterface::isValid() cannot be called before
     * submission.
     */
    public function isFormValid(): bool
    {
        $form = $this->getForm();

        return !$form->isSubmitted() || $form->isValid();
    }

    /**
     * Persist the form data.
     */
    #[LiveAction]
    #[LiveListener('save')]
    public function save(): void
    {
        try {
            $this->submitForm();
        } catch (UnprocessableEntityHttpException) {
            $this->dispatchBrowserEvent('toast.show', ['message' => 'Please correct the errors below and try again.']);

            // Form is invalid — re-render with inline validation errors.
            $this->broadcastFormState();
            return;
        }

        /** @var object $entity */
        $entity = $this->getForm()->getData();

        $this->em->persist($entity);
        $this->em->flush();

        // After persisting a new entity, update entityId so the next re-render
        // loads the persisted record rather than creating another new instance.
        if ($this->entityId === null) {
            $idValues = $this->em
                ->getClassMetadata(get_class($entity))
                ->getIdentifierValues($entity);

            $rawId = reset($idValues);
            if ($rawId !== false) {
                $this->entityId = (int) $rawId;
            }
        }

        $this->broadcastFormState();
        $this->dispatchBrowserEvent('toast.show', ['message' => 'Saved successfully!']);
    }
}
