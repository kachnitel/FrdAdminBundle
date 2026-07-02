<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Shared plumbing for admin form LiveComponents (AdminEntityForm, InlineEntityForm).
 *
 * Composed via `use` into each concrete component class independently — never via
 * one #[AsLiveComponent] class extending another. This is a deliberate
 * architectural choice, not a stylistic one.
 *
 * ## Why composition instead of inheritance
 *
 * PHP's `ReflectionClass::getAttributes()` does not return attributes declared on
 * parent classes — only ones declared directly on the reflected class. Traits do
 * not have this limitation: trait members are flattened into the using class at
 * compile time, and reflection sees them as if declared natively on it.
 *
 * Symfony UX Live Component's metadata factory relies on this kind of reflection
 * to build the client-side action/prop manifest embedded in a component's
 * `data-live-*-value` attributes. Previously, InlineEntityForm extended
 * AdminEntityForm (another #[AsLiveComponent] class). In that shape, a button
 * inside InlineEntityForm's own template calling its own `save` LiveAction via
 * `data-action="live#action" data-live-action-param="save"` intermittently fired
 * no backend request at all — consistent with the class of upstream reflection
 * gap already documented elsewhere in this project for #[LiveAction] /
 * #[LiveListener] / #[ExposeInTemplate] on parent-class methods not being picked
 * up correctly for a child #[AsLiveComponent] class. Switching to this shared
 * trait removes the LiveComponent-to-LiveComponent parent/child relationship
 * entirely, so neither component's metadata generation has to reason about the
 * other's class hierarchy.
 *
 * Note this root cause was inferred from documented upstream reflection issues
 * and the fact that the symptom disappeared after removing the inheritance
 * relationship — it was not confirmed via a minimal upstream reproduction.
 *
 * ## What this trait provides
 *
 *   - $entityClass / $formTypeClass LiveProps, identical in both components
 *   - getFormView() exposed to Twig as `form`
 *   - doGetForm() / doSubmitForm() protected proxies for ComponentWithFormTrait's
 *     private getForm()/submitForm() methods — private-to-the-composing-class, so
 *     each concrete class gets its own accessible copy via this trait
 *
 * ## Requirements for classes using this trait
 *
 *   - Must carry their own #[AsLiveComponent(name: ..., template: ...)] attribute
 *   - Must implement `instantiateForm(): FormInterface` (abstract requirement of
 *     ComponentWithFormTrait, brought in here via LiveCollectionTrait)
 *   - Must extend AbstractController (or otherwise provide createForm()) if their
 *     own instantiateForm() relies on it — this trait does not provide it
 *
 * @see AdminEntityForm
 * @see InlineEntityForm
 */
trait AdminFormComponentTrait
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
     * Fully-qualified form type class name.
     * May be a hand-written FormType or DynamicEntityFormType::class.
     */
    #[LiveProp]
    public string $formTypeClass = '';

    /**
     * Expose getFormView() to Twig templates as `form`.
     *
     * Declared directly on this trait (rather than relying solely on
     * LiveCollectionTrait's own handling of the aliased method) so that
     * #[ExposeInTemplate] is picked up reliably by every class composing this
     * trait — trait members are flattened into the using class, so this
     * attribute is visible via reflection exactly as if declared natively on
     * each concrete component.
     */
    #[ExposeInTemplate('form')]
    public function getFormView(): FormView
    {
        return $this->getFormViewFromTrait();
    }

    /**
     * Protected proxy for ComponentWithFormTrait::getForm().
     *
     * getForm() is declared private by ComponentWithFormTrait. Via standard PHP
     * trait flattening, that makes it private *to whichever concrete class
     * composes this trait* — but still fully callable from any other method
     * that ends up part of that same class, including this one. This wrapper
     * gives concrete classes a stable, protected-visibility method to call
     * instead of reaching for the trait-private method by name directly.
     *
     * @return FormInterface<object|null>
     */
    protected function doGetForm(): FormInterface
    {
        return $this->getForm();
    }

    /**
     * Protected proxy for ComponentWithFormTrait::submitForm().
     *
     * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
     *   when the submitted form is invalid
     */
    protected function doSubmitForm(): void
    {
        $this->submitForm();
    }
}
