<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * Generic live form component for admin edit and new entity pages.
 *
 * Composes AdminFormComponentTrait and AdminFormSaveTrait rather than
 * extending — see docs/FORMS.md, "Why composition, not inheritance".
 *
 * When `formTypeClass` is `DynamicEntityFormType::class`, the component
 * automatically passes the required `entity_class` and `is_root: true`
 * options so that collection associations are included in the top-level
 * form.
 *
 * Implements ObjectAuthorizedFormInterface so object-level authorization
 * (see docs/OBJECT_AUTHORIZATION.md) is enforced by
 * AdminFormComponentTrait::doSubmitForm() on every save — including a
 * fully-overridden save(), which is why the check isn't in save() itself.
 *
 * @see \Kachnitel\AdminBundle\Controller\AbstractAdminController
 * @see docs/DYNAMIC_FORM_COLLECTIONS.md
 * @see AdminFormComponentTrait
 * @see AdminFormSaveTrait
 * @see ObjectAuthorizedFormInterface
 *
 * @template TData of object|null
 * @implements AdminFormComponentInterface<TData>
 */
#[AsLiveComponent(name: 'K:Admin:EntityForm', template: '@KachnitelAdmin/components/AdminEntityForm.html.twig')]
class AdminEntityForm extends AbstractController implements AdminFormComponentInterface, ObjectAuthorizedFormInterface
{
    /** @use AdminFormComponentTrait<TData> */
    use AdminFormComponentTrait;
    use AdminFormSaveTrait;

    /**
     * Entity primary key. Null for new entities.
     */
    #[LiveProp]
    public ?int $entityId = null;

    public function __construct(protected readonly EntityManagerInterface $em) {}

    /**
     * ADMIN_NEW when this component was mounted without an entityId,
     * ADMIN_EDIT otherwise. Read at the point doSubmitForm() calls it —
     * i.e. before save()'s own post-persist entityId reassignment for a
     * freshly-created entity — so this always reflects what the save is
     * actually authorizing: creating a new row, or editing an existing one.
     */
    public function getObjectAuthorizationAttribute(): string
    {
        return $this->entityId === null ? AdminEntityVoter::ADMIN_NEW : AdminEntityVoter::ADMIN_EDIT;
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
    * @return FormInterface<TData>
     */
    public function instantiateForm(): FormInterface
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

        /** @var FormInterface<TData> */
        return $this->createForm($formTypeClass, $entity, $options);
    }
}
