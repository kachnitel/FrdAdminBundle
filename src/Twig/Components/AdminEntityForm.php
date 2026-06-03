<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Form\DynamicEntityFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Generic live form component for admin edit and new entity pages.
 *
 * Provides real-time validation as-you-type and inline save feedback
 * without full-page reloads.
 *
 * When `formTypeClass` is `DynamicEntityFormType::class`, the component
 * automatically passes the required `entity_class` option so the form type
 * can introspect Doctrine metadata without any additional configuration.
 *
 * @see \Kachnitel\AdminBundle\Controller\AbstractAdminController
 */
#[AsLiveComponent(name: 'K:Admin:EntityForm', template: '@KachnitelAdmin/components/AdminEntityForm.html.twig')]
class AdminEntityForm extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait {
        getFormView as private getFormViewFromTrait;
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
     *
     * @return FormView
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
     * is added automatically — the caller does not need to supply it.
     *
     * CSRF protection is disabled at the form level — LiveComponent handles
     * its own request-level CSRF separately.
     *
     * @return FormInterface<object>
     */
    protected function instantiateForm(): FormInterface
    {
        /** @var class-string $entityClassName */
        $entityClassName = $this->entityClass;

        if ($this->entityId !== null) {
            $entity = $this->em->find($entityClassName, $this->entityId);
        } else {
            $entity = new $entityClassName();
        }

        /** @var class-string<\Symfony\Component\Form\FormTypeInterface<object|null>> $formTypeClass */
        $formTypeClass = $this->formTypeClass;

        $options = ['csrf_protection' => false];

        /** @phpstan-ignore identical.alwaysFalse (REVIEW:) */
        if ($formTypeClass === DynamicEntityFormType::class) {
            $options['entity_class'] = $entityClassName;
            // data_class must be passed explicitly — DynamicEntityFormType cannot derive
            // it from entity_class via a lazy closure because Symfony validates data_class
            // against allowedTypes(['null', 'string']) before OptionsResolver fires closures.
            $options['data_class'] = $entityClassName;
        }

        /** @phpstan-ignore-next-line argument.templateType */
        return $this->createForm($formTypeClass, $entity, $options);
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
            // Form is invalid — re-render with inline validation errors.
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

        $this->dispatchBrowserEvent('toast.show', ['message' => 'Saved successfully!']);
    }
}
