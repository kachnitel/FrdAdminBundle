<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
// use Kachnitel\AdminBundle\Field\AdminEditabilityResolver;
use Kachnitel\AdminBundle\Twig\Runtime\AdminEntityInfoRuntime;
use Kachnitel\EntityComponentsBundle\Components\Field\EditabilityResolverInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Auto-generated form for entities that have no manually written FormType.
 *
 * Renders each editable field as its corresponding K:Entity:Field:* LiveComponent
 * in formMode=true. Saving is coordinated here via emitDown('form:save'), which
 * triggers each child field's onFormSave() listener. The header Save button
 * targets this component via the `data-admin-form` attribute, matching the same
 * contract as AdminEntityForm.
 *
 * ## Field ordering
 *
 * Fields are rendered in the same order as the entity's #[Admin(columns:)] list
 * when set, falling back to Doctrine field order. Only fields that:
 *   - are mapped by Doctrine (field or single-valued association)
 *   - pass AdminEditabilityResolver::canEdit()
 *   - have a known field component (getFieldComponentName() returns non-null)
 * are included.
 *
 * ## Save lifecycle
 *
 * 1. User clicks the header Save button → JS calls this component's `save` action
 * 2. save() emits down 'form:save' → each field's onFormSave() runs its save() lifecycle
 * 3. Fields emit 'field:saved' or 'field:save:error' back up
 * 4. onFieldSaved() / onFieldSaveError() track progress
 * 5. When all expected fields have responded, saveSuccess or hasErrors is set
 *
 * ## No FormType requirement
 *
 * This component does not use Symfony Form. Validation runs per-field inside
 * each Field component (AbstractEditableField::save()). Entity-level cross-field
 * validation is not performed; add a FormType for that use case.
 */
#[AsLiveComponent(name: 'K:Admin:AutoEntityForm', template: '@KachnitelAdmin/components/AutoEntityForm.html.twig')]
class AutoEntityForm
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    /** Fully-qualified entity class name. */
    #[LiveProp]
    public string $entityClass = '';

    /** Entity primary key. Null for new entities (not supported in auto-form). */
    #[LiveProp]
    public ?int $entityId = null;

    /**
     * Set to true after all fields have responded with field:saved.
     * Reset when save() is called again.
     */
    #[LiveProp]
    public bool $saveSuccess = false;

    /**
     * Set to true if any field emits field:save:error during a save cycle.
     * Reset when save() is called again.
     */
    #[LiveProp]
    public bool $hasErrors = false;

    /**
     * Number of fields that have responded (saved or errored) in the current save cycle.
     * Used to determine when all fields have completed.
     */
    #[LiveProp]
    public private(set) int $respondedCount = 0;

    /**
     * Total number of editable fields dispatched in the current save cycle.
     * Set at the start of save() so we know when all fields have responded.
     */
    #[LiveProp]
    public private(set) int $expectedCount = 0;

    /** @var array<string>|null Cached list of editable field names */
    private ?array $editableFields = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EditabilityResolverInterface $editabilityResolver,
        private readonly AdminEntityInfoRuntime $entityInfoRuntime,
    ) {}

    // ── Entity helpers ─────────────────────────────────────────────────────────

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
     * Filtered to properties that:
     *   - are Doctrine fields or single-valued associations
     *   - have a known K:Entity:Field:* component
     *   - pass AdminEditabilityResolver::canEdit() for the loaded entity
     *
     * @return array<string>
     */
    public function getEditableFields(): array
    {
        if ($this->editableFields !== null) {
            return $this->editableFields;
        }

        $entity = $this->getEntity();
        if ($entity === null) {
            return $this->editableFields = [];
        }

        /** @var class-string $entityClass */
        $entityClass = $this->entityClass;
        $metadata    = $this->em->getClassMetadata($entityClass);

        // Ordered candidate list: Doctrine fields then single-valued associations.
        $candidates = array_merge(
            $metadata->getFieldNames(),
            array_filter(
                $metadata->getAssociationNames(),
                fn (string $a) => $metadata->isSingleValuedAssociation($a),
            ),
        );

        $fields = [];
        foreach ($candidates as $property) {
            // Skip ID field — not editable in a form context.
            if ($property === $metadata->getSingleIdentifierFieldName()) {
                continue;
            }

            // Skip if no field component exists (e.g. json, array types).
            if ($this->entityInfoRuntime->getFieldComponentName($entity, $property) === null) {
                continue;
            }

            // Skip if editability resolver says no.
            if (!$this->editabilityResolver->canEdit($entity, $property)) {
                continue;
            }

            $fields[] = $property;
        }

        return $this->editableFields = $fields;
    }

    /**
     * Resolve the K:Entity:Field:* component name for a property.
     * Exposed so the Twig template can call it per-field.
     */
    public function getFieldComponent(string $property): ?string
    {
        $entity = $this->getEntity();
        if ($entity === null) {
            return null;
        }

        return $this->entityInfoRuntime->getFieldComponentName($entity, $property);
    }

    // ── LiveActions ────────────────────────────────────────────────────────────

    /**
     * Triggered by the header Save button via:
     *   document.querySelector('[data-admin-form]').__component.action('save')
     *
     * Resets state, counts editable fields, then emits 'form:save' down to all
     * child Field components. Each child's onFormSave() handles its own save lifecycle
     * and responds with 'field:saved' or 'field:save:error'.
     */
    #[LiveAction]
    public function save(): void
    {
        $this->saveSuccess     = false;
        $this->hasErrors       = false;
        $this->respondedCount  = 0;
        $this->expectedCount   = count($this->getEditableFields());

        if ($this->expectedCount === 0) {
            $this->saveSuccess = true;
            return;
        }

        $this->emit('form:save');
    }

    // ── LiveListeners ──────────────────────────────────────────────────────────

    /**
     * A child field completed its save successfully.
     */
    #[LiveListener('field:saved')]
    public function onFieldSaved(): void
    {
        $this->respondedCount++;
        $this->checkAllResponded();
    }

    /**
     * A child field failed validation.
     */
    #[LiveListener('field:save:error')]
    public function onFieldSaveError(): void
    {
        $this->hasErrors = true;
        $this->respondedCount++;
        $this->checkAllResponded();
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function checkAllResponded(): void
    {
        if ($this->expectedCount > 0 && $this->respondedCount >= $this->expectedCount) {
            if (!$this->hasErrors) {
                $this->saveSuccess = true;
                $this->dispatchBrowserEvent('toast.show', ['message' => 'Saved successfully!']);
            }
        }
    }
}
