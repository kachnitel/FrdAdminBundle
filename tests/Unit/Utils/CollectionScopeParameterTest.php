<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Utils;

use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Utils\CollectionScopeParameter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CollectionScopeParameter::class)]
#[Group('collection-scope')]
final class CollectionScopeParameterTest extends TestCase
{
    #[Test]
    public function encodeUsesShortClassNameIdAndProperty(): void
    {
        $this->assertSame('TestEntity:12:tagEntities', CollectionScopeParameter::encode(TestEntity::class, 12, 'tagEntities'));
    }

    #[Test]
    public function decodeReturnsShortNameIdAndProperty(): void
    {
        $this->assertSame(
            ['ownerShortName' => 'TestEntity', 'ownerId' => '12', 'property' => 'tagEntities'],
            CollectionScopeParameter::decode('TestEntity:12:tagEntities'),
        );
    }

    #[Test]
    public function decodeRoundTripsUuidIds(): void
    {
        $encoded = CollectionScopeParameter::encode(TestEntity::class, '0b9e8a0e-1c2d-4f55-8a77-5d1f6c1f3a10', 'items');

        $this->assertSame('0b9e8a0e-1c2d-4f55-8a77-5d1f6c1f3a10', CollectionScopeParameter::decode($encoded)['ownerId']);
    }

    /** @return iterable<string, array{string}> */
    public static function malformedProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'missing property' => ['TestEntity:12'];
        yield 'extra segment' => ['TestEntity:12:items:more'];
        yield 'empty id' => ['TestEntity::items'];
        yield 'namespace separator' => ['App\\Entity\\TestEntity:12:items'];
        yield 'dotted property' => ['TestEntity:12:items.name'];
        yield 'whitespace' => ['TestEntity: 12 :items'];
    }

    #[Test]
    #[DataProvider('malformedProvider')]
    public function decodeRejectsMalformedInput(string $raw): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CollectionScopeParameter::decode($raw);
    }
}
