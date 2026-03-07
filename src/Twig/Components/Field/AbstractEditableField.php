<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\Field;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Base class for all inline-editable field LiveComponents.
 *
 * ## Why entityClass + entityId instead of an entity LiveProp
 *
 * LiveComponent serializes all LiveProps to JSON for the data-live-props-value HTML attribute.
 * Entity objects cannot survive this round-trip (proxies, circular references). Storing the FQCN
 * and integer PK as scalar LiveProps avoids this; the entity is re-fetched on each request.
 *
 * ## LiveProp constraints
 *
 * - $entityClass, $entityId, $property  MUST NOT be nullable (always known)
 * - All LiveProps use a single concrete type (no union types)
 *
 * ## $currentValue hydration contract (subclass responsibility)
 *
 * Each subclass declares $currentValue with hydrateWith/dehydrateWith:
 *
 *   #[LiveProp(writable: true, hydrateWith: 'hydrateCurrentValue', dehydrateWith: 'dehydrateCurrentValue')]
 *
 * hydrateCurrentValue(null)     → re-reads value from entity  (triggered after cancelEdit)
 * hydrateCurrentValue($scalar)  → casts and returns the typed value
 * dehydrateCurrentValue($value) → returns value as-is for JSON serialization
 *
 * $entityClass, $entityId, and $property are declared in this parent class and are therefore
 * hydrated before $currentValue (PHP reflection returns parent properties first). It is safe
 * to call readValue() / getEntity() inside hydrateCurrentValue().
 *
 * ## save() / persistEdit() — template method pattern
 *
 * The base save() method owns the full lifecycle in this order:
 *
 *   1. canEdit() guard    — throws RuntimeException on access denied (BEFORE any mutation)
 *   2. persistEdit()      — subclass writes the new value to the entity
 *   3. validation         — ValidatorInterface::validateProperty() runs on the modified entity
 *                           If violations exist, $errorMessage is set and the entity is refreshed
 *                           (discarding the in-memory write). Returns early — no flush.
 *   4. flush()            — persist to DB only when validation passes
 *   5. editMode = false   — exit edit mode
 *   6. saveSuccess = true — signal a successful save for template display
 *
 * Subclasses MUST override persistEdit() instead of save() to write their value.
 * This guarantees the canEdit() guard always runs before any entity mutation.
 *
 * ## cancelEdit pattern
 *
 * Subclasses override cancelEdit() and re-read the property value from the
 * entity AFTER calling parent::cancelEdit():
 *
 *   public function cancelEdit(): void
 *   {
 *       parent::cancelEdit();                     // refreshes entity; clears resolvedEntity cache
 *       $raw = $this->readValue();                // reads from the now-refreshed entity
 *       $this->currentValue = $raw !== null ? (string) $raw : null;
 *   }
 *
 * Always call parent FIRST so that EntityManager::refresh() runs before you try to
 * read the persisted value. Calling it last would mean the edit-prop still holds the
 * user's unsaved input when the LiveComponent response is serialised.
 *
 * ## canEdit() — edit eligibility
 *
 * Three layers are checked in order, failing fast:
 *
 *   1. #[AdminColumn(editable: ...)] on the entity property:
 *      - false  → never editable (short-circuits, no voter call)
 *      - string → ExpressionLanguage expression evaluated against the entity row;
 *                 supports the same syntax as #[AdminAction(condition: ...)] e.g.:
 *                   'entity.status != "locked"'
 *                   'entity.active && is_granted("ROLE_EDITOR")'
 *      - true / absent → proceed to next check
 *
 *   2. ADMIN_EDIT voter for the entity short class.
 *
 *   3. PropertyAccessor::isWritable() — no setter ⟹ not editable.
 */
abstract class AbstractEditableField
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public bool $editMode = false;

    /** Fully-qualified entity class name. Non-nullable. */
    #[LiveProp]
    public string $entityClass = '';

    /** Integer primary key. Non-nullable. */
    #[LiveProp]
    public int $entityId = 0;

    /** Property name on the entity. Non-nullable. */
    #[LiveProp]
    public string $property = '';

    /**
     * Validation error from the most recent failed save.
     * Cleared on activateEditing() and cancelEdit().
     */
    #[LiveProp]
    public string $errorMessage = '';

    /**
     * Set to true after a successful flush, reset on the next activateEditing().
     * Templates use this to show a brief "✓ Saved" indicator in display mode.
     */
    #[LiveProp]
    public bool $saveSuccess = false;

    #[ExposeInTemplate('entity')]
    public ?object $resolvedEntity = null;

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly PropertyAccessorInterface $propertyAccessor,
        protected readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly AttributeHelper $attributeHelper,
        private readonly RowActionExpressionLanguage $expressionLanguage,
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * Populate scalar LiveProps from the entity on the initial (mount) render.
     * Not called on LiveComponent re-renders — those go through hydrateWith instead.
     *
     * @throws \InvalidArgumentException
     */
    public function mount(object $entity, string $property): void
    {
        $realClass = $entity::class;
        if (str_contains($realClass, 'Proxies\\__CG__\\')) {
            $parent = get_parent_class($entity);
            $realClass = $parent !== false ? $parent : $realClass;
        }

        if (!method_exists($entity, 'getId')) {
            throw new \InvalidArgumentException(
                "Entity {$realClass} must have a getId() method for inline editing."
            );
        }

        $id = $entity->getId();
        if (!is_int($id)) {
            throw new \InvalidArgumentException(
                "getId() on {$realClass} must return int. Got: " . get_debug_type($id)
            );
        }

        $this->entityClass    = $realClass;
        $this->entityId       = $id;
        $this->property       = $property;
        $this->resolvedEntity = $entity;
    }

    /**
     * Re-populate resolvedEntity after LiveProps are hydrated on re-renders.
     * mount() is not called on subsequent LiveComponent requests — PostHydrate fills that gap.
     */
    #[PostHydrate]
    public function initResolvedEntity(): void
    {
        if ($this->entityClass !== '' && $this->entityId !== 0) {
            $this->getEntity(); // populates $this->resolvedEntity as a side effect
        }
    }

    /** @throws \RuntimeException */
    public function getEntity(): object
    {
        if ($this->resolvedEntity !== null) {
            return $this->resolvedEntity;
        }

        /** @var class-string $class */
        $class  = $this->entityClass;
        $entity = $this->entityManager->find($class, $this->entityId);

        if ($entity === null) {
            throw new \RuntimeException("Entity {$this->entityClass}#{$this->entityId} not found.");
        }

        return $this->resolvedEntity = $entity;
    }

    /** Short class name passed as voter subject — AdminEntityVoter only accepts strings. */
    #[ExposeInTemplate]
    public function getEntityShortClass(): string
    {
        if ($this->entityClass === '') {
            return '';
        }
        $parts = explode('\\', $this->entityClass);

        return end($parts);
    }

    #[ExposeInTemplate('value')]
    public function readValue(): mixed
    {
        return $this->propertyAccessor->getValue($this->getEntity(), $this->property);
    }

    protected function writeValue(mixed $value): void
    {
        $entity = $this->getEntity();
        $this->propertyAccessor->setValue($entity, $this->property, $value);
    }

    #[ExposeInTemplate]
    public function canEdit(): bool
    {
        if ($this->entityClass === '' || $this->entityId === 0) {
            return false;
        }

        $entity = $this->getEntity();

        if (!$this->resolveEditable($entity)) {
            return false;
        }

        $isGranted  = $this->authorizationChecker->isGranted('ADMIN_EDIT', $this->getEntityShortClass());
        $isWritable = $this->propertyAccessor->isWritable($entity, $this->property);

        return $isGranted && $isWritable;
    }

    // ── LiveActions ────────────────────────────────────────────────────────────

    #[LiveAction]
    public function activateEditing(): void
    {
        if ($this->canEdit()) {
            $this->editMode     = true;
            $this->errorMessage = '';
            $this->saveSuccess  = false;
        }
    }

    /**
     * Exit edit mode and discard unsaved input by refreshing the entity from the database.
     *
     * ## Subclass contract
     *
     * Subclasses that hold an additional LiveProp representing the user's in-progress edit
     * (e.g. $currentValue, $dateValue, $selectedId) MUST override this method and re-read
     * the property value from the entity AFTER calling parent::cancelEdit():
     *
     *   ```php
     *   #[LiveAction]
     *   public function cancelEdit(): void
     *   {
     *       parent::cancelEdit();                     // refreshes entity; clears resolvedEntity cache
     *       $raw = $this->readValue();                // reads from the now-refreshed entity
     *       $this->currentValue = $raw !== null ? (string) $raw : null;
     *   }
     *   ```
     *
     * Always call parent FIRST so that EntityManager::refresh() runs before you try to
     * read the persisted value. Calling it last would mean the edit-prop still holds the
     * user's unsaved input when the LiveComponent response is serialised.
     */
    #[LiveAction]
    public function cancelEdit(): void
    {
        $this->editMode       = false;
        $this->errorMessage   = '';

        $this->entityManager->refresh($this->getEntity());
    }

    /**
     * Persist the edited value to the database.
     *
     * ## Lifecycle (called by save())
     *
     *   canEdit() guard → persistEdit() → validate → flush → editMode=false, saveSuccess=true
     *
     * save() is declared final on the lifecycle: the canEdit() guard always runs before
     * this method is called. Subclasses MUST NOT override save() — override persistEdit()
     * to write their value to the entity via writeValue() or direct adder/remover calls.
     *
     * ## Validation
     *
     * After persistEdit() runs, save() calls ValidatorInterface::validateProperty() against
     * the modified entity property. If violations exist, errorMessage is populated,
     * the entity is refreshed from the database (discarding the in-memory write), and save()
     * returns early without flushing. The component stays in edit mode so the user can
     * correct the input.
     *
     * @throws \RuntimeException from subclass implementations when the entity state is invalid
     *                           (e.g. target entity not found for relationships)
     */
    protected function persistEdit(): void {}

    /**
     * Guard → write → validate → flush.
     *
     * This is the #[LiveAction] entry point. It calls persistEdit() — which subclasses
     * override — only after verifying canEdit(). This ensures the permission check always
     * runs before any entity mutation, regardless of the subclass implementation.
     */
    #[LiveAction]
    public function save(): void
    {
        if (!$this->canEdit()) {
            throw new \RuntimeException('Access denied for editing this field.');
        }

        $this->errorMessage = '';
        $this->persistEdit();

        $errors = $this->validator->validateProperty($this->getEntity(), $this->property);
        if (count($errors) > 0) {
            $this->errorMessage = (string) $errors->get(0)->getMessage();
            // Discard the in-memory write by refreshing from DB.
            // refresh() updates the entity object in-place; resolvedEntity already points to it.
            // resolvedEntity is not a LiveProp so it is always re-populated via PostHydrate
            // on the next request regardless.
            $this->entityManager->refresh($this->getEntity());

            return;
        }

        $this->entityManager->flush();
        $this->editMode    = false;
        $this->saveSuccess = true;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Resolve the #[AdminColumn(editable: ...)] attribute for the current property.
     *
     * ## Decision tree (checked in order):
     *
     *  1. editable === false  → false (explicit opt-out; short-circuits everything)
     *  2. editable is string  → evaluate expression; entity default bypassed entirely
     *  3. editable === true   → true  (explicit opt-in; entity default bypassed)
     *  4. editable === null   → read entity-level #[Admin(enableInlineEdit: ...)]
     *
     * The expression (case 2) receives the current user's AuthorizationChecker so that
     * is_granted() works exactly as in #[AdminAction(condition: ...)].
     */
    private function resolveEditable(object $entity): bool
    {
        /** @var AdminColumn|null $attr */
        $attr = $this->attributeHelper->getPropertyAttribute(
            $entity::class,
            $this->property,
            AdminColumn::class,
        );

        // 1. Explicit false — never editable regardless of entity flag or voter
        if ($attr !== null && $attr->editable === false) {
            return false;
        }

        // 2. Expression string — evaluate; entity default bypassed entirely
        if ($attr !== null && is_string($attr->editable)) {
            return $this->expressionLanguage->evaluate(
                $attr->editable,
                $entity,
                $this->authorizationChecker,
            );
        }

        // 3. Explicit true — opt-in overrides entity default; proceed to voter + writable
        if ($attr !== null && $attr->editable === true) {
            return true;
        }

        // 4. null (no attribute, or explicit null) — defer to entity-level enableInlineEdit
        /** @var Admin|null $adminAttr */
        $adminAttr = $this->attributeHelper->getAttribute($entity::class, Admin::class);

        return $adminAttr !== null && $adminAttr->isEnableInlineEdit();
    }

    #[ExposeInTemplate]
    public function getLabel(): string
    {
        // e.g. "publishedAt" → "Published At"
        return ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $this->property));
    }
}
