<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Utils;

/**
 * Compact, URL-friendly encoding of a collection scope: `Owner:id:property`
 * (e.g. `Order:12:lines`), carried in the `collection` query parameter of an
 * entity index page.
 *
 * Only the *shape* is validated here. Whether the owner is an admin entity, exists,
 * and may be shown to the current user is decided by CollectionScopeResolver on
 * every query, so a hand-edited URL can never widen access.
 *
 * @see \Kachnitel\AdminBundle\Service\CollectionScopeResolver
 */
final class CollectionScopeParameter
{
    public const QUERY_KEY = 'collection';

    private const PATTERN = '/^(?<owner>\w+):(?<id>[\w-]+):(?<property>\w+)$/D';

    /**
     * @param class-string $ownerClass
     */
    public static function encode(string $ownerClass, string|int $ownerId, string $property): string
    {
        return sprintf('%s:%s:%s', (new \ReflectionClass($ownerClass))->getShortName(), $ownerId, $property);
    }

    /**
     * @return array{ownerShortName: string, ownerId: string, property: string}
     *
     * @throws \InvalidArgumentException when the value is not `Owner:id:property`
     */
    public static function decode(string $raw): array
    {
        if (preg_match(self::PATTERN, $raw, $matches) !== 1) {
            throw new \InvalidArgumentException('Malformed collection scope parameter.');
        }

        return [
            'ownerShortName' => $matches['owner'],
            'ownerId'        => $matches['id'],
            'property'       => $matches['property'],
        ];
    }
}
