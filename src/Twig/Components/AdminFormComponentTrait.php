<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Contracts\Service\Attribute\Required;
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
 *   - doSubmitForm() additionally enforces object-level authorization on the
 *     bound entity when the composing class implements
 *     ObjectAuthorizedFormInterface — see that interface's docblock for why
 *     this specific method, rather than save(), is where that check lives
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
 * @see ObjectAuthorizedFormInterface
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
     * Populated via the #[Required] setter below, which only fires through
     * real Symfony DI (container-built components, including LiveComponent's
     * own instantiation path). A component constructed directly via `new` —
     * as several existing test doubles in this bundle do (e.g.
     * TestableAdminEntityForm, TestableInlineEntityForm) — never receives
     * this property unless the test calls setObjectAuthorizationChecker()
     * itself first. doSubmitForm() below checks isset() before use and
     * throws a descriptive \LogicException rather than letting PHP's own
     * "must not be accessed before initialization" error surface, which
     * gives no hint about *why* — only classes implementing
     * ObjectAuthorizedFormInterface are affected; every other component
     * composing this trait never touches this property at all.
     */
    private ObjectAuthorizationChecker $objectAuthChecker;

    #[Required]
    public function setObjectAuthorizationChecker(ObjectAuthorizationChecker $objectAuthChecker): void
    {
        $this->objectAuthChecker = $objectAuthChecker;
    }

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
     * Protected proxy for ComponentWithFormTrait::submitForm(). Binds
     * submitted/live-synced request data onto the form's underlying entity,
     * then — when the composing class implements
     * ObjectAuthorizedFormInterface — runs object-level authorization
     * against the freshly-bound entity before returning.
     *
     * The authorization check runs unconditionally on every call,
     * independent of whichever save() implementation eventually calls
     * doSubmitForm() — including a save() that overrides
     * AdminFormSaveTrait's entirely. See ObjectAuthorizedFormInterface's
     * docblock for why this specific method is where that check has to
     * live to actually be non-bypassable.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
     *   when the submitted form is invalid
     * @throws \LogicException
     *   when the composing class implements ObjectAuthorizedFormInterface
     *   but $objectAuthChecker was never initialized — see that property's
     *   docblock. This indicates a test or other caller constructed the
     *   component directly instead of through the container, and forgot to
     *   call setObjectAuthorizationChecker() first.
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     *   when ObjectAuthorizedFormInterface is implemented and the current
     *   user is not granted access to the bound entity — see
     *   ObjectAuthorizationChecker for when this can actually happen.
     */
    public function doSubmitForm(): void
    {
        $this->submitForm();

        if ($this instanceof ObjectAuthorizedFormInterface) {
            if (!isset($this->objectAuthChecker)) {
                throw new \LogicException(sprintf(
                    '%s implements ObjectAuthorizedFormInterface but its ObjectAuthorizationChecker '
                    . 'was never set. This is normally injected automatically via the #[Required] '
                    . 'setObjectAuthorizationChecker() setter when the component is built through the '
                    . 'Symfony container. If you constructed this component directly (e.g. `new %s(...)` '
                    . 'in a unit test), call setObjectAuthorizationChecker() yourself before calling '
                    . 'doSubmitForm() / save().',
                    static::class,
                    static::class,
                ));
            }

            $entity = $this->doGetForm()->getData();

            if (!is_object($entity)) {
                return;
            }

            $this->objectAuthChecker->denyAccessUnlessGranted(
                $this->getObjectAuthorizationAttribute(),
                $entity,
            );
        }
    }
}
