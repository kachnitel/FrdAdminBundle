<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Twig\Components\AdminFormComponentInterface;
use Kachnitel\AdminBundle\Twig\Components\AdminFormComponentTrait;
use Kachnitel\AdminBundle\Twig\Components\AdminFormSaveTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * Demonstrates — and regression-tests — the recommended shape for custom
 * form components: compose AdminFormComponentTrait + AdminFormSaveTrait
 * directly, rather than extend AdminEntityForm. This is exactly what
 * FORMS.md's "Custom form components" section now documents; a real
 * PurchaseOrderForm-style component should look like this.
 *
 * Deliberately has zero relationship to AdminEntityForm — proves
 * broadcastFormState()/save() work via pure composition, sidestepping the
 * parent/child #[AsLiveComponent] reflection question entirely, rather than
 * relying on it being safe for this specific attribute (that's what
 * OverridingSaveAdminEntityForm + AdminEntityFormPreRerenderInheritanceTest
 * cover, for the inheritance shape specifically).
 *
 * @see \Kachnitel\AdminBundle\Tests\Functional\SaveButtonIntegrationTest
 * @implements AdminFormComponentInterface<object|null>
 * @use AdminFormComponentTrait<object|null>
 */
#[AsLiveComponent(name: 'Test:Form:Composed', template: '@KachnitelAdmin/components/AdminEntityForm.html.twig')]
final class ComposedFormComponent extends AbstractController implements AdminFormComponentInterface
{
    /** @use AdminFormComponentTrait<object|null> */
    use AdminFormComponentTrait;
    use AdminFormSaveTrait;

    #[LiveProp]
    public ?int $entityId = null;

    public function __construct(protected readonly EntityManagerInterface $em) {}

    /**
     * @return FormInterface<object|null>
     */
    public function instantiateForm(): FormInterface
    {
        /** @var class-string $entityClassName */
        $entityClassName = $this->entityClass;

        $entity = $this->entityId !== null
            ? $this->em->find($entityClassName, $this->entityId)
            : null;

        /** @var FormInterface<object|null> $form */
        $form = $this->createForm($this->formTypeClass, $entity, ['csrf_protection' => false]);

        return $form;
    }
}
