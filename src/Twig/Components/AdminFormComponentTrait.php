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
 * Composed via `use` into each concrete component independently — never via
 * one #[AsLiveComponent] class extending another. See FORMS.md, "Why
 * composition, not inheritance" for why this matters.
 *
 * Provides:
 *   - $entityClass / $formTypeClass LiveProps
 *   - getFormView() exposed to Twig as `form`
 *   - doGetForm() / doSubmitForm() protected proxies for
 *     ComponentWithFormTrait's private getForm()/submitForm()
 *
 * Classes composing this trait must:
 *   - carry their own #[AsLiveComponent(name: ..., template: ...)]
 *   - implement instantiateForm(): FormInterface (required by
 *     ComponentWithFormTrait, via LiveCollectionTrait)
 *   - extend AbstractController (or otherwise provide createForm()) if their
 *     instantiateForm() needs it — this trait doesn't provide it
 *
 * @see AdminEntityForm
 * @see InlineEntityForm
 *
 * @template TData of object|null
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
     * Fully-qualified form type class name — a hand-written FormType or
     * DynamicEntityFormType::class.
     */
    #[LiveProp]
    public string $formTypeClass = '';

    /**
     * Exposes getFormView() to Twig as `form`. Declared here (not left to
     * LiveCollectionTrait's aliased method alone) so #[ExposeInTemplate] is
     * picked up reliably via trait flattening on every composing class.
     */
    #[ExposeInTemplate('form')]
    public function getFormView(): FormView
    {
        return $this->getFormViewFromTrait();
    }

    /**
     * Protected proxy for ComponentWithFormTrait::getForm() (private to the
     * composing class via trait flattening).
     *
    * @return FormInterface<TData>
     */
    public function doGetForm(): FormInterface
    {
        return $this->getForm();
    }

    /**
     * Protected proxy for ComponentWithFormTrait::submitForm().
     *
     * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
     *   when the submitted form is invalid
     */
    public function doSubmitForm(): void
    {
        $this->submitForm();
    }
}
