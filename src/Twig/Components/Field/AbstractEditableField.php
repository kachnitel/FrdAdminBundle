<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\Field;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
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
        return $this->authorizationChecker->isGranted('ADMIN_EDIT', $this->getEntityShortClass());
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
     * Exit edit mode and refresh the entity.
     *
     * Subclasses MUST override this to null $currentValue first:
     *
     *   public function cancelEdit(): void
     *   {
     *       $this->currentValue = null;
     *       parent::cancelEdit();
     *   }
     *
     * null is dehydrated to the client as null. On the next request hydrateCurrentValue(null)
     * re-reads the entity value, replacing the stale pre-cancel typed value without needing
     * an explicit sync method.
     */
    #[LiveAction]
    public function cancelEdit(): void
    {
        $this->editMode = false;
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
