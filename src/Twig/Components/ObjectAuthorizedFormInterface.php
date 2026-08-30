<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

/**
 * Opt-in contract for AdminFormComponentTrait-composing components that want
 * object-level authorization enforced on the bound entity unconditionally —
 * every time doSubmitForm() runs, including when save() is overridden
 * entirely.
 *
 * ## Why this exists: save() overrides are documented and supported
 *
 * FORMS.md's "Custom form components" section documents overriding save()
 * completely — "your own persist/flush/toast, no call back into the
 * trait's version" — as fully supported, and OverridingSaveAdminEntityForm
 * exists specifically to prove it. A check inlined directly in
 * AdminFormSaveTrait::save()'s body would silently stop running for any
 * component exercising that documented pattern, which is exactly the kind
 * of footgun a security check can't have.
 *
 * doSubmitForm() (declared on AdminFormComponentTrait, not
 * AdminFormSaveTrait) is the one call every save flow — default or
 * overridden — must make to retrieve the bound entity at all; there's no
 * other way to read live-synced form data. That makes it the correct,
 * genuinely non-bypassable integration point, the same way
 * AdminFormSaveTrait::broadcastFormState() survives a save() override by
 * living in a separate #[PreReRender] hook rather than in save() itself —
 * except #[PreReRender] fires only after a LiveAction (including a
 * mutating one) has already run, so it can't be used to *prevent*
 * anything. This interface is the equivalent mechanism for a check that
 * must run before persistence.
 *
 * ## Opt-in, not automatic for every composing class
 *
 * AdminEntityForm and InlineEntityForm — the bundle's own components —
 * implement this. A fully custom component (composing
 * AdminFormComponentTrait + AdminFormSaveTrait directly, per FORMS.md's
 * PurchaseOrderForm example) does not get this for free: implement this
 * interface to opt in, or call
 * ObjectAuthorizationChecker::denyAccessUnlessGranted() yourself wherever
 * suits your component. Implementing it is safe to do unconditionally
 * either way — ObjectAuthorizationChecker remains a no-op for any entity
 * without #[Admin(enableObjectAuth: true)], so this interface
 * never starts enforcing anything for entities that haven't opted in.
 *
 * @see AdminFormComponentTrait::doSubmitForm()
 * @see \Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker
 */
interface ObjectAuthorizedFormInterface
{
    /**
     * The AdminEntityVoter::ADMIN_* attribute to check the bound entity
     * against, immediately after doSubmitForm() binds submitted data onto
     * it and before any persistence.
     */
    public function getObjectAuthorizationAttribute(): string;
}
