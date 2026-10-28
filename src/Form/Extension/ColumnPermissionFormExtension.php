<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Form\Extension;

use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Dev diagnostics for #[ColumnPermission] on the auto-generated (DynamicEntityFormType) form.
 *
 * Enforcement itself is NOT done here: AdminColumnEditabilityResolver (the admin-bundle
 * implementation of dynamic-form-bundle's FieldEditabilityResolverInterface) removes
 * ADMIN_SHOW-denied fields via canEdit() and renders ADMIN_EDIT-denied fields disabled
 * via isReadOnly(). This extension never mutates the form.
 *
 * ## Dev diagnostics (kernel.debug only)
 *
 * A restricted column that then fails validation while CREATING an entity (a disabled
 * field with NotBlank, or a removed field that is required) makes the entity impossible
 * to create for users lacking the role. That is a configuration problem, not a bug here:
 * give the column a default, set it in a listener, or align the "new" permission with the
 * column role. In debug a warning is logged; production behaviour is unchanged.
 * "Creating" means the form was built with no data (null), as AdminEntityForm and
 * InlineEntityForm do for new entities. Only the root form is inspected.
 */
final class ColumnPermissionFormExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly ColumnPermissionService $columnPermissions,
        private readonly bool $debug = false,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public static function getExtendedTypes(): iterable
    {
        return [DynamicEntityFormType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $class = $this->diagnosableClass($options);
        if ($class === null || $this->logger === null) {
            return;
        }

        [$removed, $disabled] = $this->classifyRestrictedColumns($builder, $class);

        if ($removed !== [] || $disabled !== []) {
            $this->registerCreateDiagnostics($builder, $class, $removed, $disabled, $this->logger);
        }
    }

    /**
     * @param array<string, mixed> $options
     * @return class-string|null null when diagnostics are off or the class is unknown
     */
    private function diagnosableClass(array $options): ?string
    {
        if (!$this->debug) {
            return null;
        }

        $class = $options['data_class'] ?? $options['entity_class'] ?? null;

        return is_string($class) && class_exists($class) ? $class : null;
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param class-string                $class
     * @return array{0: list<string>, 1: list<string>} [ADMIN_SHOW-denied (removed), ADMIN_EDIT-denied (disabled)]
     */
    private function classifyRestrictedColumns(FormBuilderInterface $builder, string $class): array
    {
        $removed = [];
        $disabled = [];

        foreach (array_keys($this->columnPermissions->getColumnPermissionMap($class)) as $property) {
            if (!$this->columnPermissions->canPerformAction($class, $property, AdminEntityVoter::ADMIN_SHOW)) {
                $removed[] = $property;
            } elseif ($builder->has($property) && !$this->columnPermissions->canPerformAction($class, $property, AdminEntityVoter::ADMIN_EDIT)) {
                $disabled[] = $property;
            }
        }

        return [$removed, $disabled];
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param list<string>                $removed  ADMIN_SHOW-denied properties
     * @param list<string>                $disabled ADMIN_EDIT-denied properties
     */
    private function registerCreateDiagnostics(
        FormBuilderInterface $builder,
        string $class,
        array $removed,
        array $disabled,
        LoggerInterface $logger,
    ): void {
        $isNew = false;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (PreSetDataEvent $event) use (&$isNew): void {
            $isNew = $event->getData() === null;
        });

        // Priority below 0: Symfony's ValidationListener runs at 0 and must have added errors already.
        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            static function (PostSubmitEvent $event) use (&$isNew, $class, $removed, $disabled, $logger): void {
                $form = $event->getForm();
                if (!$isNew || !$form->isRoot()) {
                    return;
                }

                $offenders = self::findOffenders($form, $removed, $disabled);
                foreach ($offenders as $property => $denied) {
                    $logger->warning(sprintf(
                        'Column "%s" on %s is %s-restricted but fails validation when creating a new entity, '
                        . 'so users without access cannot create it. Give it a default, set it in a listener, '
                        . 'or align the "new" permission with the column role.',
                        $property,
                        $class,
                        $denied,
                    ), ['entity' => $class, 'property' => $property, 'denied' => $denied]);
                }
            },
            -1000,
        );
    }

    /**
     * @param FormInterface<mixed> $form
     * @param list<string>         $removed
     * @param list<string>         $disabled
     * @return array<string, string> property => denied action
     */
    private static function findOffenders(FormInterface $form, array $removed, array $disabled): array
    {
        $offenders = [];

        foreach ($disabled as $property) {
            if ($form->has($property) && \count($form->get($property)->getErrors(true)) > 0) {
                $offenders[$property] = AdminEntityVoter::ADMIN_EDIT;
            }
        }

        foreach ($form->getErrors() as $error) {
            $property = self::violatedProperty($error);
            if ($property !== null && \in_array($property, $removed, true)) {
                $offenders[$property] = AdminEntityVoter::ADMIN_SHOW;
            }
        }

        return $offenders;
    }

    /** Top-level property a root-form constraint violation points at, if any. */
    private static function violatedProperty(FormError $error): ?string
    {
        $cause = $error->getCause();
        if (!$cause instanceof ConstraintViolationInterface) {
            return null;
        }

        $path = preg_replace('/^data\./', '', $cause->getPropertyPath()) ?? '';

        return preg_split('/[.\[]/', $path)[0] ?? null;
    }
}
