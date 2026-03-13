<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Minimal form type for InlineEditEntity.
 * Required so that AdminRouteRuntime::hasForm('InlineEditEntity') returns true,
 * making the Edit action visible on show/edit page headers.
 *
 * @extends AbstractType<InlineEditEntity>
 */
class InlineEditEntityFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InlineEditEntity::class,
        ]);
    }
}