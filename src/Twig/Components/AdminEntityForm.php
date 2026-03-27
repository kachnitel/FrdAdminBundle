<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
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
 * Usage in Twig (edit):
 * ```twig
 * <twig:AdminEntityForm
 *     entityClass="App\Entity\Product"
 *     entityId="{{ entity.id }}"
 *     formTypeClass="App\Form\ProductFormType"
 * />
 * ```
 *
 * Usage in Twig (new):
 * ```twig
 * <twig:AdminEntityForm
 *     entityClass="App\Entity\Product"
 *     formTypeClass="App\Form\ProductFormType"
 * />
 * ```
 *
 * The Save button in the page header uses `el.__component.action('save');` to trigger
 * a `save` action.
 *
 * ## CSRF handling
 *
 * The form is created with `csrf_protection: false`. LiveComponent manages its
 * own request integrity (via its own CSRF token on the Ajax request), so adding
 * a second CSRF token inside the form would cause spurious validation failures
 * since the token is never included in `formValues`.
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
     * Fully-qualified form type class name (e.g. App\Form\ProductFormType).
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
     * Build the Symfony form bound to the entity loaded from the database
     * (or a freshly-instantiated entity for the "new" action).
     *
     * CSRF protection is disabled at the form level — LiveComponent handles
     * its own request-level CSRF separately.
     *
     * @return FormInterface<object>
     */
    protected function instantiateForm(): FormInterface
    {
        /** @var class-string $class */
        $class = $this->entityClass;

        if ($this->entityId !== null) {
            $entity = $this->em->find($class, $this->entityId);
        } else {
            $entity = new $class();
        }

        return $this->createForm($this->formTypeClass, $entity, [
            'csrf_protection' => false,
        ]);
    }

    /**
     * Persist the form data.
     *
     * Triggered by the `save` LiveComponent event emitted by `K:Components:LiveEmitTrigger`
     * (the header Save button), and directly as a LiveAction for tests via `->call('save')`.
     *
     * `submitForm()` integrates with the LiveComponent lifecycle and avoids double-submission
     * issues. When the form is invalid it throws `UnprocessableEntityHttpException`; we catch
     * that to stay on the page and let the component re-render with inline errors.
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
