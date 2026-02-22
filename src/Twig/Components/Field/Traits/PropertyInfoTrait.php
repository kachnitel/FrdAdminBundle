<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\Field\Traits;

use Symfony\Bridge\Doctrine\PropertyInfo\DoctrineExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyWriteInfo;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Provides Doctrine-backed type introspection for inline-editable field components.
 *
 * Requires the using class to have $entityManager (EntityManagerInterface) and
 * $property (string) available — both provided by AbstractEditableField.
 */
trait PropertyInfoTrait
{
    private DoctrineExtractor $doctrineExtractor;
    private ReflectionExtractor $reflectionExtractor;

    /**
     * Injects the ReflectionExtractor from the container when symfony/property-info is enabled
     * (framework.property_info: true, which is the Symfony full-stack default).
     *
     * Falls back to a new ReflectionExtractor() with the default EnglishInflector when the
     * service is not registered. The only observable difference is that apps with a custom
     * inflector configured on the container service will silently get English inflection instead.
     *
     * @note If you observe inflection mismatches (e.g. addCategory not found for $categories),
     *       ensure framework.property_info is enabled in your Symfony configuration.
     */
    #[Required]
    public function initPropertyInfoExtractors(?ReflectionExtractor $reflectionExtractor = null): void
    {
        $this->doctrineExtractor    = new DoctrineExtractor($this->entityManager);
        $this->reflectionExtractor  = $reflectionExtractor ?? new ReflectionExtractor();
    }

    protected function getPropertyType(): ?string
    {
        $type = $this->doctrineExtractor->getType($this->entityClass, $this->property);

        return $type?->__toString();
    }

    protected function isRequired(): bool
    {
        $type = $this->doctrineExtractor->getType($this->entityClass, $this->property);

        return $type !== null
            && !$type->isNullable()
            && $this->propertyAccessor->isWritable($this->getEntity(), $this->property);
    }

    protected function isNullable(): bool
    {
        $type = $this->doctrineExtractor->getType($this->entityClass, $this->property);

        return $type !== null && $type->isNullable();
    }

    /**
     * Resolve the target entity class for any association type (ManyToOne, OneToOne,
     * OneToMany, ManyToMany). Replaces the previous TypeInfo-based implementation that
     * only supported collection types.
     *
     * @return class-string|null  null when $property is not a Doctrine association
     */
    protected function getTargetEntityClass(): ?string
    {
        $metadata = $this->entityManager->getClassMetadata($this->entityClass);

        if (!$metadata->hasAssociation($this->property)) {
            return null;
        }

        /** @var class-string */
        return $metadata->getAssociationTargetClass($this->property);
    }

    /**
     * Resolve the adder and remover method names for a collection-valued association
     * using Symfony's ReflectionExtractor and its built-in EnglishInflector.
     *
     * The inflector correctly singularises common English plurals:
     *   $categories → addCategory / removeCategory
     *   $tags       → addTag / removeTag
     *
     * @return array{adder: string, remover: string}
     *
     * @throws \RuntimeException when no adder/remover pair can be found, e.g. when the
     *   entity only exposes direct collection access with no dedicated add/remove methods.
     *   Fix: add addX(RelatedEntity $x): void and removeX(RelatedEntity $x): void methods.
     *
     * @note Falls back to ReflectionExtractor with EnglishInflector if symfony/property-info
     *   is not enabled in the container. Enable framework.property_info for custom inflectors.
     */
    protected function getCollectionMutators(): array
    {
        $writeInfo = $this->reflectionExtractor->getWriteInfo($this->entityClass, $this->property);

        if ($writeInfo === null || $writeInfo->getType() !== PropertyWriteInfo::TYPE_ADDER_AND_REMOVER) {
            throw new \RuntimeException(sprintf(
                'Cannot mutate collection "%s::$%s": no adder/remover pair found. '
                . 'Add addX()/removeX() methods to your entity, or make the property directly writable.',
                $this->entityClass,
                $this->property,
            ));
        }

        return [
            'adder'   => $writeInfo->getAdderInfo()->getName(),
            'remover' => $writeInfo->getRemoverInfo()->getName(),
        ];
    }
}
