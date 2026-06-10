<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Hand-written FormType for OrderLineItem — used in tests to verify that a custom
 * FormType can override the auto-generated child form and include the parent
 * back-reference when the developer explicitly wants it.
 *
 * @extends AbstractType<OrderLineItem>
 */
class OrderLineItemFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextType::class, ['empty_data' => ''])
            ->add('quantity', IntegerType::class)
            ->add('order', EntityType::class, [
                'class'    => OrderWithLines::class,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OrderLineItem::class]);
    }
}
