<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Minimal form type for EntityWithRowActions used by AdminEntityForm functional tests.
 *
 * Registered automatically by TestKernel since form_namespace points to
 * Kachnitel\AdminBundle\Tests\Fixtures\ and the class name follows the
 * {EntityShortName}FormType convention.
 *
 * @extends AbstractType<EntityWithRowActions>
 */
class EntityWithRowActionsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label'       => 'Name',
            // empty_data: '' prevents Symfony from mapping null to a non-nullable string property
            // when the user submits an empty field. Validation (NotBlank) still fires on ''.
            'empty_data'  => '',
            'constraints' => [new NotBlank(message: 'Name is required.')],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EntityWithRowActions::class,
        ]);
    }
}
