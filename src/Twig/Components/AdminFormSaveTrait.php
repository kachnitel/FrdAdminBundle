<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\PreReRender;

/**
 * Standard save lifecycle for admin entity-form LiveComponents: persist,
 * flush, entityId tracking for new entities, success/error toast, and the
 * validity broadcast K:Admin:Action:Save listens for.
 *
 * Composed (via `use`), never inherited, by both AdminEntityForm and any
 * custom form component — same rationale as AdminFormComponentTrait (see
 * that trait's docblock, and InlineEntityForm's, for the full history):
 * one #[AsLiveComponent] class extending another risks PHP reflection not
 * reliably discovering #[LiveAction]/#[LiveListener] on an inherited,
 * un-overridden method. Traits don't have this problem — a trait's methods
 * are reflected as if declared directly on the consuming class, so
 * composing this trait is safe regardless of whether the consuming class
 * overrides save() or not, and regardless of which specific attribute is
 * involved (unlike relying on inheritance + hoping #[PreReRender]
 * specifically is one of the unaffected ones — see
 * AdminEntityFormPreRerenderInheritanceTest, which confirmed that for the
 * old design but doesn't generalise to every future attribute).
 *
 * This trait is what closed the actual production bug (PurchaseOrderForm-
 * shaped: extends AdminEntityForm, overrides save() with its own
 * persist/flush/toast, never calls broadcastFormState()). Extracting
 * save()/broadcastFormState() here means a consuming class can override
 * save() completely and broadcastFormState() still fires via
 * #[PreReRender] regardless — nothing to remember, nothing to call.
 *
 * Requires the consuming class to also compose AdminFormComponentTrait (for
 * doSubmitForm()/doGetForm() and the emit()/dispatchBrowserEvent() plumbing
 * it brings in), and to declare its own EntityManagerInterface $em and
 * ?int $entityId — exactly what AdminEntityForm does, and what any custom
 * form component composing this trait should do too. See FORMS.md's
 * "Custom form components" section for the full recommended shape.
 *
 * @property-read EntityManagerInterface $em
 * @property ?int $entityId
 */
trait AdminFormSaveTrait
{
    /**
     * Broadcasts the form's current validity to any listening
     * K:Admin:Action:Save button, purely for a non-blocking visual hint (see
     * SaveButton's docblock for why this doesn't gate its disabled state).
     *
     * #[PreReRender], not an explicit call inside save(): fires on every
     * render regardless of which #[LiveAction] ran or how a consuming class
     * implements save() — see trait docblock.
     *
     * priority: -10 is required, not cosmetic. It must run AFTER
     * ComponentWithFormTrait::submitFormOnRender() (priority 0 — "higher
     * called earlier"), which is what actually submits the live-bound form
     * data for this render. At the default priority or higher,
     * isFormValid() would see a not-yet-submitted form and always report
     * valid — the exact bug this replaced when broadcastFormState() was
     * briefly #[PostHydrate].
     *
     * K:Admin:Action:Save is a sibling, not a child (rendered in the page
     * header block vs. the form component's content block), so LiveProp
     * parent/child binding isn't available; broadcast events are the only
     * channel between the two.
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
     * Whether the form is currently valid. True for an untouched form (not
     * yet submitted), since FormInterface::isValid() cannot be called before
     * submission.
     */
    public function isFormValid(): bool
    {
        $form = $this->doGetForm();

        return !$form->isSubmitted() || $form->isValid();
    }

    /**
     * Persist the form data.
     *
     * A consuming class overrides this entirely for custom save logic by
     * declaring its own save() — standard PHP class-overrides-trait-method
     * rules. broadcastFormState() keeps firing via #[PreReRender]
     * regardless of what an override does or doesn't call; there's no
     * parent::save() to remember here.
     */
    #[LiveAction]
    #[LiveListener('save')]
    public function save(): void
    {
        try {
            $this->doSubmitForm();
        } catch (UnprocessableEntityHttpException) {
            $this->dispatchBrowserEvent('toast.show', ['message' => 'Please correct the errors below and try again.']);
            return;
        }

        /** @var object $entity */
        $entity = $this->doGetForm()->getData();

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
