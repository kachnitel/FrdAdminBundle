<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Twig\Runtime\AdminRouteRuntime;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\PreReRender;

/**
 * Standard save lifecycle for admin entity-form LiveComponents: persist,
 * flush, entityId tracking, success/error toast, validity broadcast for
 * K:Admin:Action:Save, and a redirect to the edit page after creating a
 * new entity (see buildCreateRedirect()). Editing an existing entity never
 * redirects — it re-renders in place as usual.
 *
 * Composed via `use`, not inherited — see FORMS.md, "Why composition, not
 * inheritance" for why. `broadcastFormState()` stays safe even if a consuming
 * class fully overrides save(): it's a separate `#[PreReRender]` hook, not
 * something save() has to remember to call.
 *
 * Object-level authorization is NOT checked here. It runs in
 * AdminFormComponentTrait::doSubmitForm() (called by save() below, but also
 * by any override of save()) when the composing class implements
 * ObjectAuthorizedFormInterface — deliberately not inlined in this method's
 * body, so it can't be silently skipped the way inlining it here would let
 * an overridden save() skip it. See ObjectAuthorizedFormInterface's
 * docblock for the full reasoning.
 *
 * Requires the consuming class to also compose AdminFormComponentTrait,
 * and to declare its own EntityManagerInterface $em and ?int $entityId
 * (see AdminEntityForm, and FORMS.md's "Custom form components" section).
 *
 * @property-read EntityManagerInterface $em
 * @property ?int $entityId
 * @property ?string $saveError
 *
 * @phpstan-require-implements AdminFormComponentInterface
 */
trait AdminFormSaveTrait
{
    /**
     * Populated via the #[Required] setter below, which only fires through
     * real Symfony DI (container-built components, including LiveComponent's
     * own instantiation path). A component constructed directly via `new` —
     * as several existing test doubles in this bundle do — never receives
     * this property unless the test calls setAdminRouteRuntime() itself
     * first. buildCreateRedirect() below checks isset() before use and
     * throws a descriptive \LogicException rather than letting PHP's own
     * "must not be accessed before initialization" error surface uncaught
     * (that error is a plain \Error, not an \Exception, so it would not be
     * caught by buildCreateRedirect()'s own \Exception catch below —
     * matches the same isset()-guard pattern used by
     * AdminFormComponentTrait::$objectAuthChecker for the same reason).
     */
    private AdminRouteRuntime $adminRouteRuntime;

    #[Required]
    public function setAdminRouteRuntime(AdminRouteRuntime $adminRouteRuntime): void
    {
        $this->adminRouteRuntime = $adminRouteRuntime;
    }

    /**
     * Broadcasts form validity to any listening K:Admin:Action:Save button
     * (non-blocking visual hint only — see SaveButton's docblock).
     *
     * priority: -10 is required: it must run after
     * ComponentWithFormTrait::submitFormOnRender() (priority 0), which
     * submits the live-bound form data for this render. At a higher
     * priority, isFormValid() would see a not-yet-submitted form and
     * always report valid.
     */
    #[PreReRender(priority: -10)]
    public function broadcastFormState(): void
    {
        $this->emit(
            'admin:form:state',
            ['valid' => $this->isFormValid() ? 1 : 0],
            'K:Admin:Action:Save'
        );
    }

    /**
     * True for an untouched (not-yet-submitted) form, since
     * FormInterface::isValid() can't be called before submission.
     */
    public function isFormValid(): bool
    {
        $form = $this->doGetForm();

        return !$form->isSubmitted() || $form->isValid();
    }

    /**
     * Persist the form data. Override entirely for custom save logic —
     * broadcastFormState() keeps firing via #[PreReRender] regardless.
     *
     * Return type is ?RedirectResponse so overrides stay covariant even
     * if they never redirect.
     */
    #[LiveAction]
    #[LiveListener('save')]
    public function save(): ?RedirectResponse
    {
        $this->saveError = null;

        try {
            $this->doSubmitForm();
        } catch (UnprocessableEntityHttpException) {
            $this->emit('admin:form:result', ['status' => 'error'], 'K:Admin:Action:Save');
            $this->dispatchBrowserEvent('toast.show', ['message' => 'Please correct the errors below and try again.']);
            return null;
        } catch (AccessDeniedException) {
            $message = 'You are not allowed to manage this entity.';
            $this->saveError = $message;
            $this->emit('admin:form:result', ['status' => 'error'], 'K:Admin:Action:Save');
            $this->dispatchBrowserEvent('toast.show', ['message' => $message]);
            return null;
        }

        /** @var object $entity */
        $entity = $this->doGetForm()->getData();

        // Object-level authorization for this save already ran inside the
        // doSubmitForm() call above (AdminFormComponentTrait), against this
        // same $entity in its post-submission state — see that method's
        // docblock. A denied save returns before reaching this persistence
        // block and reports the failure through the toast event above.
        $wasNew = $this->entityId === null;

        $this->em->persist($entity);
        $this->em->flush();

        if ($wasNew) {
            $idValues = $this->em
                ->getClassMetadata(get_class($entity))
                ->getIdentifierValues($entity);

            $rawId = reset($idValues);
            if (is_numeric($rawId)) {
                $this->entityId = (int) $rawId;
            }
        }

        if ($wasNew && $this->entityId !== null) {
            $redirect = $this->buildCreateRedirect($entity);
            if ($redirect !== null) {
                return $redirect;
            }
        }

        $this->emit('admin:form:result', ['status' => 'success'], 'K:Admin:Action:Save');
        $this->dispatchBrowserEvent('toast.show', ['message' => 'Saved successfully!']);

        return null;
    }

    /**
     * Redirect to a freshly created entity's own edit page. Returns null
     * (falls through to the stay-in-place toast) when no edit URL can be
     * generated, e.g. enable_generic_controller: false with no
     * #[AdminRoutes] override.
     *
     * Deliberately skips AdminRouteRuntime::hasRoute() — its generic-route
     * fallback always reports 'edit' as available, so only actually
     * generating the URL can tell a real gap from a healthy default.
     * Protected so a consuming class can override just the redirect
     * target (e.g. to a show page).
     *
     * @throws \LogicException
     *   when $adminRouteRuntime was never initialized — see that property's
     *   docblock. This indicates a test or other caller constructed the
     *   component directly instead of through the container, and forgot to
     *   call setAdminRouteRuntime() first. Checked before the try/catch
     *   below so it isn't silently swallowed by the \Exception catch —
     *   \LogicException extends \Exception.
     */
    protected function buildCreateRedirect(object $entity): ?RedirectResponse
    {
        if (!isset($this->adminRouteRuntime)) {
            throw new \LogicException(sprintf(
                '%s uses AdminFormSaveTrait but its AdminRouteRuntime was never set. '
                . 'This is normally injected automatically via the #[Required] '
                . 'setAdminRouteRuntime() setter when the component is built through the '
                . 'Symfony container. If you constructed this component directly (e.g. `new %s(...)` '
                . 'in a unit test), call setAdminRouteRuntime() yourself before calling '
                . 'save() (or buildCreateRedirect() directly).',
                static::class,
                static::class,
            ));
        }

        try {
            $url = $this->adminRouteRuntime->getPath($entity, 'edit');
        } catch (\Exception) {
            return null;
        }

        return new RedirectResponse($url);
    }
}
