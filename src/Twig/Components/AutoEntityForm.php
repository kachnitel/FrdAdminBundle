<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Service\DoctrineValueCoercer;
use Kachnitel\AdminBundle\Twig\Runtime\AdminEntityInfoRuntime;
use Kachnitel\EntityComponentsBundle\Components\Field\EditabilityResolverInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Auto-generated form for entities that have no manually written FormType.
 *
 * Supports both creating new entities and editing existing ones.
 *
 * ## Edit mode (entityId provided)
 *
 * Renders each editable property as its K:Entity:Field:* LiveComponent with
 * formMode=true. Saving is coordinated via emitDown('form:save'), which triggers
 * each child field's onFormSave() listener. The component tracks how many fields
 * have responded and sets saveSuccess when all complete without errors.
 *
 * ## New mode (entityId is null)
 *
 * Cannot use child Field LiveComponents because they require an integer entity ID
 * to load from the database. Instead, the component renders plain HTML inputs
 * that sync to the $formValues LiveProp via data-model. The parent's save()
 * instantiates a fresh entity, coerces all form values to their proper PHP types
 * via DoctrineValueCoercer, writes them via PropertyAccessor, validates, and
 * persists. After a successful persist+flush, entityId is set so that subsequent
 * re-renders switch to edit mode (consistent with AdminEntityForm behaviour).
 *
 * ## Header Save button contract
 *
 * The `data-admin-form` attribute on the root element matches the same convention
 * as AdminEntityForm, so the existing `_save_button.html.twig` works unchanged:
 *   document.querySelector('[data-admin-form]').__component.action('save')
 */
#[AsLiveComponent(name: 'K:Admin:AutoEntityForm', template: '@KachnitelAdmin/components/AutoEntityForm.html.twig')]
class AutoEntityForm
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    /** Fully-qualified entity class name. */
    #[LiveProp]
    public string $entityClass = '';

    /**
     * Entity primary key. Null for new entities.
     * Set after a successful persist so subsequent re-renders switch to edit mode.
     */
    #[LiveProp]
    public ?int $entityId = null;

    /**
     * Raw form values for new-entity mode, keyed by property name.
     * Synced from plain HTML inputs via data-model="formValues[propName]".
     *
     * @var array<string, mixed>
     */
    #[LiveProp(writable: true)]
    public array $formValues = [];

    /**
     * Validation errors for new-entity mode, keyed by property name.
     *
     * @var array<string, string>
     */
    #[LiveProp]
    public array $formErrors = [];

    /** True after all fields saved (edit mode) or entity persisted (new mode). */
    #[LiveProp]
    public bool $saveSuccess = false;

    /** True if any field emitted field:save:error in the current save cycle (edit mode). */
    #[LiveProp]
    public bool $hasErrors = false;

    /** Number of child fields that have responded in the current edit-mode save cycle. */
    #[LiveProp]
    public int $respondedCount = 0;

    /** Total child fields dispatched in the current edit-mode save cycle. */
    #[LiveProp]
    public int $expectedCount = 0;

    /** @var array<string>|null Cached editable field list */
    private ?array $editableFields = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EditabilityResolverInterface $editabilityResolver,
        private readonly AdminEntityInfoRuntime $entityInfoRuntime,
        private readonly DoctrineValueCoercer $coercer,
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly ValidatorInterface $validator,
    ) {}

    // ── Accessors ──────────────────────────────────────────────────────────────

    public function isNew(): bool
    {
        return $this->entityId === null;
    }

    public function getEntity(): ?object
    {
        if ($this->entityClass === '' || $this->entityId === null) {
            return null;
        }

        /** @var class-string $class */
        $class = $this->entityClass;

        return $this->em->find($class, $this->entityId);
    }

    /**
     * Ordered list of property names to render as editable fields.
     *
     * In edit mode: filtered by editability resolver (voter + attribute checks).
     * In new mode: filtered by attribute inspection only (no entity instance).
     *
     * The ID field is always excluded.
     *
     * @return array<string>
     */
    public function getEditableFields(): array
    {
        if ($this->editableFields !== null) {
            return $this->editableFields;
        }

        /** @var class-string $entityClass */
        $entityClass = $this->entityClass;

        if ($entityClass === '') { // @phpstan-ignore identical.alwaysFalse
            return $this->editableFields = [];
        }

        $metadata   = $this->em->getClassMetadata($entityClass);
        $idField    = $metadata->getSingleIdentifierFieldName();

        $candidates = array_merge(
            $metadata->getFieldNames(),
            array_filter(
                $metadata->getAssociationNames(),
                fn (string $a) => $metadata->isSingleValuedAssociation($a),
            ),
        );

        $entity = $this->isNew() ? null : $this->getEntity();
        $fields = [];

        foreach ($candidates as $property) {
            if ($property === $idField) {
                continue;
            }

            if ($this->isNew()) {
                // New mode: attribute-only check — no entity instance available.
                if (!$this->isPropertyEditableByAttribute($entityClass, $property)) {
                    continue;
                }
                // In new mode we render plain inputs, so no field component needed.
                $fields[] = $property;
            } else {
                // Edit mode: full check via resolver + component availability.
                if ($entity === null) {
                    continue;
                }

                if ($this->entityInfoRuntime->getFieldComponentName($entity, $property) === null) {
                    continue;
                }

                if (!$this->editabilityResolver->canEdit($entity, $property)) {
                    continue;
                }

                $fields[] = $property;
            }
        }

        return $this->editableFields = $fields;
    }

    /**
     * Resolve the K:Entity:Field:* component name for a property (edit mode only).
     */
    public function getFieldComponent(string $property): ?string
    {
        $entity = $this->getEntity();
        if ($entity === null) {
            return null;
        }

        return $this->entityInfoRuntime->getFieldComponentName($entity, $property);
    }

    /**
     * Return the Doctrine field type string for a property.
     * Used by the new-mode template to pick the right <input type="...">.
     */
    public function getFieldType(string $property): string
    {
        /** @var class-string $entityClass */
        $entityClass = $this->entityClass;
        $metadata    = $this->em->getClassMetadata($entityClass);

        if ($metadata->hasAssociation($property)) {
            return 'relation';
        }

        return $metadata->getTypeOfField($property) ?? 'string';
    }

    /**
     * Whether the given property column is nullable in Doctrine.
     */
    public function isNullable(string $property): bool
    {
        /** @var class-string $entityClass */
        $entityClass = $this->entityClass;
        $metadata    = $this->em->getClassMetadata($entityClass);

        if (!$metadata->hasField($property)) {
            return true;
        }

        return $metadata->getFieldMapping($property)->nullable ?? false;
    }

    // ── LiveActions ────────────────────────────────────────────────────────────

    /**
     * Triggered by the header Save button.
     *
     * Edit mode: emitDown('form:save') to all child Field components.
     * New mode:  coerce + validate + persist directly.
     */
    #[LiveAction]
    public function save(): void
    {
        $this->saveSuccess    = false;
        $this->hasErrors      = false;
        $this->respondedCount = 0;
        $this->formErrors     = [];

        if ($this->isNew()) {
            $this->saveNew();
        } else {
            $this->saveEdit();
        }
    }

    // ── LiveListeners (edit mode) ──────────────────────────────────────────────

    #[LiveListener('field:saved')]
    public function onFieldSaved(): void
    {
        $this->respondedCount++;
        $this->checkAllResponded();
    }

    #[LiveListener('field:save:error')]
    public function onFieldSaveError(): void
    {
        $this->hasErrors = true;
        $this->respondedCount++;
        $this->checkAllResponded();
    }

    // ── Private: edit mode ─────────────────────────────────────────────────────

    private function saveEdit(): void
    {
        $this->expectedCount = count($this->getEditableFields());

        if ($this->expectedCount === 0) {
            $this->saveSuccess = true;
            return;
        }

        $this->emit('form:save');
    }

    private function checkAllResponded(): void
    {
        if ($this->expectedCount > 0 && $this->respondedCount >= $this->expectedCount) {
            if (!$this->hasErrors) {
                $this->saveSuccess = true;
                $this->dispatchBrowserEvent('toast.show', ['message' => 'Saved successfully!']);
            }
        }
    }

    // ── Private: new mode ──────────────────────────────────────────────────────

    private function saveNew(): void
    {
        /** @var class-string $entityClass */
        $entityClass = $this->entityClass;

        $reflection = new \ReflectionClass($entityClass);
        $entity     = $reflection->newInstanceWithoutConstructor();

        $metadata     = $this->em->getClassMetadata($entityClass);
        $typedValues  = $this->coercer->coerceAll($metadata, $this->formValues);

        foreach ($typedValues as $property => $value) {
            try {
                $this->propertyAccessor->setValue($entity, $property, $value);
            } catch (\Throwable) {
                // Silently skip unwritable properties — validation will surface real issues.
            }
        }

        $violations = $this->validator->validate($entity);

        if (count($violations) > 0) {
            $this->hasErrors = true;

            foreach ($violations as $violation) {
                $prop = ltrim((string) $violation->getPropertyPath(), '.');
                if (!isset($this->formErrors[$prop])) {
                    $this->formErrors[$prop] = (string) $violation->getMessage();
                }
            }

            return;
        }

        $this->em->persist($entity);
        $this->em->flush();

        // Switch to edit mode so the next render uses Field components.
        $idValues       = $metadata->getIdentifierValues($entity);
        $rawId          = reset($idValues);
        $this->entityId = $rawId !== false ? (int) $rawId : null;

        $this->saveSuccess   = true;
        $this->editableFields = null; // clear cache — now in edit mode
        $this->formValues    = [];

        $this->dispatchBrowserEvent('toast.show', ['message' => 'Created successfully!']);
    }

    // ── Private: attribute helpers ─────────────────────────────────────────────

    /**
     * Attribute-only editability check used in new mode where no entity instance exists.
     *
     * Returns true when:
     *   - The entity class has #[Admin(enableInlineEdit: true)], OR
     *   - The property has #[AdminColumn(editable: true)]
     *
     * Intentionally skips voter and setter checks — those would require an entity instance.
     *
     * @param class-string $entityClass
     */
    private function isPropertyEditableByAttribute(string $entityClass, string $property): bool
    {
        try {
            $classReflection = new \ReflectionClass($entityClass);

            // Check entity-level opt-in first.
            $adminAttrs = $classReflection->getAttributes(\Kachnitel\AdminBundle\Attribute\Admin::class);
            if (!empty($adminAttrs)) {
                /** @var \Kachnitel\AdminBundle\Attribute\Admin $adminAttr */
                $adminAttr = $adminAttrs[0]->newInstance();
                if ($adminAttr->isEnableInlineEdit()) {
                    return true;
                }
            }

            // Check per-property opt-in.
            if (!$classReflection->hasProperty($property)) {
                return false;
            }

            $propAttrs = $classReflection->getProperty($property)
                ->getAttributes(\Kachnitel\AdminBundle\Attribute\AdminColumn::class);

            if (empty($propAttrs)) {
                return false;
            }

            /** @var \Kachnitel\AdminBundle\Attribute\AdminColumn $col */
            $col = $propAttrs[0]->newInstance();

            return $col->editable === true;
        } catch (\Throwable) {
            return false;
        }
    }
}
