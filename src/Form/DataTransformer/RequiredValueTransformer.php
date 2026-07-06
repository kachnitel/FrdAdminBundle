<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Rejects `null` reaching a DB-required (nullable: false) scalar/date/time/
 * enum field's model data, routing it through the same
 * TransformationFailedException -> not-synchronized -> DataMapper-skips-
 * the-write path Symfony already uses for a malformed NumberType/DateType
 * submission.
 *
 * Without this, a blank required field on a live-resubmitted form (see
 * ComponentWithFormTrait::submitFormOnRender(), which runs before every
 * render) would otherwise reach the DataMapper as a "successfully
 * transformed" null, and PropertyAccessor would throw a raw TypeError
 * against the entity's typed, non-nullable property instead of a form
 * validation error.
 *
 * This transformer never sees the raw view-level submission directly.
 * DoctrineFormTypeMapper sets `empty_data: ''` (not `null`) for every
 * guarded field; Symfony's own core transformers (NumberToLocalizedString,
 * DateTimeToHtml5LocalDateTime, ChoiceToValue) all correctly and safely
 * resolve that '' to `null` on their own — it's only a *literal* null
 * reaching those transformers directly that several of them reject outright
 * as a type error, which is why '' rather than null is what's fed in. This
 * transformer runs after that resolution, as a model transformer, and only
 * ever has to reject the one value they hand it: null.
 *
 * The message passed to TransformationFailedException is never shown to
 * the user — set the visible text via the field's `invalid_message` option.
 * transform() is the identity function; this only guards view -> model.
 *
 * @implements DataTransformerInterface<mixed, mixed>
 */
final class RequiredValueTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): mixed
    {
        return $value;
    }

    public function reverseTransform(mixed $value): mixed
    {
        if ($value === null) {
            throw new TransformationFailedException('Value is required.');
        }

        return $value;
    }
}
