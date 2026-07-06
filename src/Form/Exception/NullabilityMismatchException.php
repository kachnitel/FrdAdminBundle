<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Form\Exception;

/**
 * Thrown when a Doctrine field's DB-level `nullable` mapping disagrees with
 * its PHP property's own type nullability in a way DoctrineFormTypeMapper
 * cannot safely resolve on its own: Doctrine says the column may hold NULL,
 * but the PHP property type does not allow null.
 *
 * A NULL database row would crash the moment that property is read (either
 * during Doctrine hydration or while DynamicEntityFormType builds the
 * FormView); the form layer has no safe way to paper over that, so it fails
 * loudly and early — at form-build time, naming the exact field — rather
 * than leaving it to surface later as an unexplained TypeError.
 *
 * Fix the entity, not this exception: either widen the PHP property to a
 * nullable type, or set nullable: false on the Doctrine column mapping so
 * the two agree. The reverse combination (Doctrine nullable: false + a
 * nullable PHP property) is fine and common — it's exactly the shape a
 * "new entity" form needs for a required field that has no value yet — and
 * never triggers this exception.
 */
class NullabilityMismatchException extends \LogicException
{
    public static function forField(string $entityClass, string $fieldName): self
    {
        return new self(sprintf(
            '%s::$%s is mapped nullable: true in Doctrine but its PHP property type does not '
            . 'allow null. A NULL database row would crash on hydration. Either widen the '
            . 'property to a nullable type, or set nullable: false on the #[ORM\Column] mapping '
            . 'so the two agree.',
            $entityClass,
            $fieldName,
        ));
    }
}
