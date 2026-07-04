<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\AdminAction;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Save button for the entity edit/new page header.
 *
 * Rendered as a sibling of K:Admin:EntityForm — header block vs. content
 * block, see admin/edit.html.twig and admin/new.html.twig — not a child, so
 * LiveProp parent/child binding (updateFromParent, as used by BatchActionTrait)
 * isn't available. State is synchronised entirely through LiveComponent's
 * broadcast event system instead.
 *
 * Flow:
 *   1. Click → triggerSave() sets $saving = true (this component re-renders
 *      itself immediately as disabled/"Saving…") and broadcasts 'save'.
 *   2. K:Admin:EntityForm's existing #[LiveListener('save')] save() method
 *      picks this up — same contract the old onclick handler relied on.
 *   3. AdminEntityForm broadcasts 'admin:form:state' once save() completes
 *      (only then — see AdminEntityForm::broadcastFormState() docblock).
 *   4. onFormStateChanged() updates $saving/$valid here.
 *
 * @see \Kachnitel\AdminBundle\Twig\Components\AdminEntityForm
 */
#[AsLiveComponent('K:Admin:Action:Save', template: '@KachnitelAdmin/components/AdminAction/SaveButton.html.twig')]
class SaveButton
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    /**
     * True while a save request is in flight. The button shows "Saving…" and
     * is disabled to prevent double submission. The sole driver of the
     * `disabled` attribute — see class docblock for why $valid isn't.
     */
    #[LiveProp]
    public bool $saving = false;

    /**
     * Last-known validity broadcast by K:Admin:EntityForm after a save
     * attempt. Visual-only (aria-invalid hint) — does not gate `disabled`.
     */
    #[LiveProp]
    public bool $valid = true;

    #[LiveAction]
    public function triggerSave(): void
    {
        $this->saving = true;
        $this->emit('save');
    }

    /**
     * @param int<0, 1> $valid 0 = invalid, 1 = valid
     */
    #[LiveListener('admin:form:state')]
    public function onFormStateChanged(#[LiveArg] int $valid): void
    {
        $this->valid = $valid === 1;
        $this->saving = false;
    }
}
