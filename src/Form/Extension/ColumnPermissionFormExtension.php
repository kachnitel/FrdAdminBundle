<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Form\Extension;

use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use InvalidArgumentException;

/**
 * Applies #[ColumnPermission] to the auto-generated (DynamicEntityFormType) form.
 *
 *   ADMIN_SHOW denied -> field is removed (no value leak, nothing to submit)
 *   ADMIN_EDIT denied -> field is kept but disabled; Symfony ignores submitted
 *                        data for disabled fields, so this is server-enforced
 *
 * Runs after DynamicEntityFormType::buildForm(), so every generated child is
 * already present. Hand-written FormTypes are not covered.
 */
final class ColumnPermissionFormExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly ColumnPermissionService $columnPermissions,
    ) {}

    public static function getExtendedTypes(): iterable
    {
        return [DynamicEntityFormType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $class = $options['data_class'] ?? $options['entity_class'] ?? null;
        if (!is_string($class) || !class_exists($class)) {
            throw new InvalidArgumentException('DynamicEntityFormType requires a valid data_class or entity_class for column permissions.');
        }

        foreach (array_keys($this->columnPermissions->getColumnPermissionMap($class)) as $property) {
            if (!$builder->has($property)) {
                continue;
            }

            if (!$this->columnPermissions->canPerformAction($class, $property, AdminEntityVoter::ADMIN_SHOW)) {
                $builder->remove($property);
                continue;
            }

            if (!$this->columnPermissions->canPerformAction($class, $property, AdminEntityVoter::ADMIN_EDIT)) {
                $builder->get($property)->setDisabled(true);
            }
        }
    }
}