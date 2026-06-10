<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Form;

use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Form event listener that evaluates expression-based editability rules
 * after form data is bound.
 *
 * This allows #[AdminColumn(editable: 'expression')] to work correctly
 * in child forms (e.g., items in LiveCollectionType) where the entity
 * instance is only available after the form is created.
 *
 * For each field that has an expression, the listener:
 * 1. Evaluates the expression against the bound entity
 * 2. Removes the field if the expression evaluates to false
 * 3. Keeps the field if the expression evaluates to true
 *
 * This runs on PRE_SET_DATA so that field removal happens before
 * data is actually bound to the form, preventing data binding errors.
 */
final class DynamicFormEditabilityListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly RowActionExpressionLanguage $expressionLanguage,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        /** @var class-string */
        private readonly string $entityClass,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SET_DATA => 'onPreSetData',
        ];
    }

    /**
     * Evaluate expression-based editability rules before data binding.
     *
     * This is called when $form->setData($entity) happens (including
     * automatic binding during form creation).
     */
    public function onPreSetData(FormEvent $event): void
    {
        $entity = $event->getData();

        // Skip if no entity data available (new form, not yet bound)
        if ($entity === null || !is_object($entity)) {
            return;
        }

        // Skip if entity class doesn't match expected type
        if (!$entity instanceof $this->entityClass) {
            return;
        }

        $form = $event->getForm();

        // Check each field's editability based on expressions
        foreach ($form->all() as $fieldName => $child) {
            /** @var string $fieldName */
            if ($this->shouldRemoveFieldDueToExpression($fieldName, $entity)) {
                $form->remove($fieldName);
            }
        }
    }

    /**
     * Check if a field should be removed based on expression evaluation.
     *
     * Returns true if:
     * - The field has #[AdminColumn(editable: 'expression')]
     * - The expression evaluates to false
     *
     * @noinspection PhpUnhandledExceptionInspection
     */
    private function shouldRemoveFieldDueToExpression(string $fieldName, object $entity): bool
    {
        $reflection = new \ReflectionClass($entity::class);

        if (!$reflection->hasProperty($fieldName)) {
            return false;
        }

        $attributes = $reflection->getProperty($fieldName)->getAttributes(AdminColumn::class);

        if (empty($attributes)) {
            return false;
        }

        /** @var AdminColumn $col */
        $col = $attributes[0]->newInstance();

        // Only handle expression strings; explicit true/false are handled during buildForm
        if (!is_string($col->editable)) {
            return false;
        }

        // Evaluate expression: if it returns false, remove the field
        $result = $this->expressionLanguage->evaluate(
            $col->editable,
            $entity,
            $this->authorizationChecker,
        );

        return !$result;
    }
}
