<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\Field;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
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
 * ## cancelEdit pattern
 *
 * Subclasses override cancelEdit() to null $currentValue before calling parent::cancelEdit():
 *
 *   public function cancelEdit(): void
 *   {
 *       $this->currentValue = null;   // dehydrates as null → hydrateCurrentValue re-reads entity
 *       parent::cancelEdit();
 *   }
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

    #[ExposeInTemplate('entity')]
    public ?object $resolvedEntity = null;

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly PropertyAccessorInterface $propertyAccessor,
        protected readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly AttributeHelper $attributeHelper,
        private readonly RowActionExpressionLanguage $expressionLanguage,
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

    /**
     * Resolve the #[AdminColumn(editable: ...)] attribute for the current property.
     *
     * Returns true when the attribute is absent, editable is true, or a string expression passes.
     * Returns false when editable is false or the expression evaluates to false.
     *
     * The expression receives the current user's AuthorizationChecker so that
     * is_granted() works exactly as it does in #[AdminAction(condition: ...)].
     */
    private function resolveEditable(object $entity): bool
    {
        /** @var AdminColumn|null $attr */
        $attr = $this->attributeHelper->getPropertyAttribute(
            $entity::class,
            $this->property,
            AdminColumn::class,
        );

        if ($attr === null) {
            return true;
        }

        if ($attr->editable === false) {
            return false;
        }

        if (is_string($attr->editable)) {
            return $this->expressionLanguage->evaluate(
                $attr->editable,
                $entity,
                $this->authorizationChecker,
            );
        }

        // editable === true
        return true;
    }

    #[ExposeInTemplate]
    public function getLabel(): string
    {
        // e.g. "publishedAt" → "Published At"
        return ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $this->property));
    }

    // ── LiveActions ────────────────────────────────────────────────────────────

    #[LiveAction]
    public function activateEditing(): void
    {
        if ($this->canEdit()) {
            $this->editMode = true;
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
     *
     * ## Why not "null the LiveProp first"?
     *
     * An earlier design proposed nulling $currentValue before calling parent so that
     * hydrateCurrentValue(null) would re-read the entity on the next request. The concrete
     * implementations do not use this pattern because it requires a round-trip: the client
     * receives null, sends a new request, and only then sees the correct value. Re-reading
     * directly after parent::cancelEdit() is simpler and correct in one step.
     *
     * ## Fields that use hydrateWith / dehydrateWith
     *
     * StringField, IntField, and FloatField declare their $currentValue LiveProp with
     * hydrateWith/dehydrateWith. They still follow the same "parent first, re-read after"
     * pattern in cancelEdit(). The hydrateWith pair handles re-renders triggered by other
     * LiveActions, not the cancel flow.
     */
    #[LiveAction]
    public function cancelEdit(): void
    {
        $this->editMode         = false;
        $this->resolvedEntity   = null; // force re-fetch so readValue() sees refreshed state
        $this->entityManager->refresh($this->getEntity());
    }

    /**
     * Flush to DB and exit edit mode.
     * Subclasses call parent::save() after writing $currentValue to the entity.
     */
    #[LiveAction]
    public function save(): void
    {
        if (!$this->canEdit()) {
            throw new \RuntimeException('Access denied for editing this field.');
        }

        $this->entityManager->flush();
        $this->editMode = false;
    }
}