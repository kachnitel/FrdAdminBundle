<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/**
 * @template TData of object|null
 */
interface AdminFormComponentInterface
{
    /**
     * @return FormInterface<TData>
     */
    public function instantiateForm(): FormInterface;

    /**
     * @return FormInterface<TData>
     */
    public function doGetForm(): FormInterface;

    public function doSubmitForm(): void;

    public function getFormView(): FormView;
}
