<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Kachnitel\AdminBundle\Twig\Components\AdminEntityForm;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveListener;

/**
 * Reproduces the exact shape of the production bug found in PurchaseOrderForm:
 * a custom form component that overrides save() entirely — its own
 * persist/flush, its own custom toast copy — without calling parent::save()
 * or broadcastFormState() itself.
 *
 * Exists to prove AdminEntityForm::broadcastFormState() — a #[PreReRender]
 * hook declared only on the parent, not overridden here — still fires for
 * this child with zero cooperation required from the override. See
 * broadcastFormState()'s docblock for the open question this test is
 * actually settling: whether an inherited #[PreReRender] hook is reliable
 * across this parent/child #[AsLiveComponent] relationship, given
 * InlineEntityForm's documented history with the same shape.
 *
 * @see \Kachnitel\AdminBundle\Tests\Functional\SaveButtonIntegrationTest
 */
#[AsLiveComponent(name: 'Test:Form:OverridingSave', template: '@KachnitelAdmin/components/AdminEntityForm.html.twig')]
final class OverridingSaveAdminEntityForm extends AdminEntityForm
{
    /**
     * Deliberately duplicates AdminEntityForm::save()'s persistence logic
     * instead of calling parent::save() — this is the shape of the bug
     * being regression-tested. Deliberately does NOT call
     * broadcastFormState(): that's the whole point.
     */
    #[LiveAction]
    #[LiveListener('save')]
    public function save(): void
    {
        try {
            $this->doSubmitForm();
        } catch (UnprocessableEntityHttpException) {
            $this->dispatchBrowserEvent('toast.show', ['message' => 'Fix the errors.']);
            return;
        }

        /** @var object $entity */
        $entity = $this->doGetForm()->getData();

        $this->em->persist($entity);
        $this->em->flush();

        $this->dispatchBrowserEvent('toast.show', ['message' => 'Custom entity saved!']);
    }
}
