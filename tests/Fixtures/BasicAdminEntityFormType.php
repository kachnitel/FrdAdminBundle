<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Minimal form type for BasicAdminEntity.
 *
 * Required so that AdminRouteRuntime::hasForm('BasicAdminEntity') returns true,
 * which allows the default Edit row action to pass the isActionAccessible() check
 * and appear on the show page header.
 *
 * @extends AbstractType<BasicAdminEntity>
 */
class BasicAdminEntityFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label'       => 'Name',
            'empty_data'  => '',
            'constraints' => [new NotBlank(message: 'Name is required.')],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BasicAdminEntity::class,
        ]);
    }
}
