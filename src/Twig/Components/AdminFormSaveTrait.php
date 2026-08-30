<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Twig\Runtime\AdminRouteRuntime;
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
 *
 * @phpstan-require-implements AdminFormComponentInterface
 */
trait AdminFormSaveTrait
{
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
        try {
            $this->doSubmitForm();
        } catch (UnprocessableEntityHttpException) {
            $this->dispatchBrowserEvent('toast.show', ['message' => 'Please correct the errors below and try again.']);
            return null;
        }

        /** @var object $entity */
        $entity = $this->doGetForm()->getData();

        // Object-level authorization for this save already ran inside the
        // doSubmitForm() call above (AdminFormComponentTrait), against this
        // same $entity in its post-submission state — see that method's
        // docblock. An AccessDeniedException there propagates out of this
        // try block same as UnprocessableEntityHttpException would, since
        // it isn't caught by the catch clause above.
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
     */
    protected function buildCreateRedirect(object $entity): ?RedirectResponse
    {
        try {
            $url = $this->adminRouteRuntime->getPath($entity, 'edit');
        } catch (\Exception) {
            return null;
        }

        return new RedirectResponse($url);
    }
}
