<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Minimal form type for ObjectAuthEntity used by object-authorization
 * functional tests. Passed explicitly as formTypeClass when mounting
 * AdminEntityForm / InlineEntityForm directly via createLiveComponent(),
 * mirroring TestEntityFormType's role for TestEntity.
 *
 * @extends AbstractType<ObjectAuthEntity>
 */
class ObjectAuthEntityFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label'       => 'Name',
                // empty_data: '' prevents Symfony from mapping null to a non-nullable
                // string property when the user submits an empty field.
                'empty_data'  => '',
                'constraints' => [new NotBlank(message: 'Name is required.')],
            ])
            ->add('kind', TextType::class, [
                'label'      => 'Kind',
                'empty_data' => ObjectAuthEntity::KIND_ALLOWED,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ObjectAuthEntity::class,
        ]);
    }
}
