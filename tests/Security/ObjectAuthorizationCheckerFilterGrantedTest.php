<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Security;

use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Kachnitel\AdminBundle\Security\SkipsUnauthorizedEntitiesTrait;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\TestCase;

/**
 * Covers the shared skip-on-deny helper used by batch actions. The checker
 * itself is covered by ObjectAuthorizationCheckerTest, while the call sites
 * are covered by EntityListBatchServiceTest and ArchiveButtonTest.
 */
#[PHPUnit\CoversTrait(SkipsUnauthorizedEntitiesTrait::class)]
#[PHPUnit\Group('security')]
#[PHPUnit\Group('object-authorization')]
final class ObjectAuthorizationCheckerFilterGrantedTest extends TestCase
{
    private FilterAuthorizedEntitiesHarness $harness;

    protected function setUp(): void
    {
        $this->harness = new FilterAuthorizedEntitiesHarness();
    }

    #[PHPUnit\Test]
    public function returnsOnlyGrantedEntitiesPreservingTheirOriginalKeys(): void
    {
        $allowed = new \stdClass();
        $denied  = new \stdClass();

        $checker = $this->createStub(ObjectAuthorizationChecker::class);
        $checker->method('isGranted')->willReturnMap([
            ['ADMIN_DELETE', $allowed, true],
            ['ADMIN_DELETE', $denied, false],
        ]);

        $result = $this->harness->filter('ADMIN_DELETE', [
            7  => $allowed,
            12 => $denied,
        ], $checker);

        $this->assertSame([7 => $allowed], $result);
    }

    #[PHPUnit\Test]
    public function returnsEverythingWhenObjectAuthorizationIsNotEnabledForTheEntityClass(): void
    {
        $entityA = new \stdClass();
        $entityB = new \stdClass();

        $checker = $this->createMock(ObjectAuthorizationChecker::class);
        $checker->expects($this->exactly(2))->method('isGranted')->willReturn(true);

        $result = $this->harness->filter('ADMIN_DELETE', [
            1 => $entityA,
            2 => $entityB,
        ], $checker);

        $this->assertSame([1 => $entityA, 2 => $entityB], $result);
    }

    #[PHPUnit\Test]
    public function returnsEmptyArrayWhenEverythingIsDenied(): void
    {
        $checker = $this->createStub(ObjectAuthorizationChecker::class);
        $checker->method('isGranted')->willReturn(false);

        $result = $this->harness->filter('ADMIN_ARCHIVE', [
            1 => new \stdClass(),
            2 => new \stdClass(),
        ], $checker);

        $this->assertSame([], $result);
    }

    #[PHPUnit\Test]
    public function returnsEmptyArrayForEmptyInputWithoutConsultingAuthorizationChecker(): void
    {
        $checker = $this->createMock(ObjectAuthorizationChecker::class);
        $checker->expects($this->never())->method('isGranted');

        $this->assertSame([], $this->harness->filter('ADMIN_DELETE', [], $checker));
    }

    #[PHPUnit\Test]
    public function preservesStringKeysAsWellAsIntKeys(): void
    {
        $entity = new \stdClass();

        $checker = $this->createStub(ObjectAuthorizationChecker::class);
        $checker->method('isGranted')->willReturn(true);

        $result = $this->harness->filter('ADMIN_EDIT', ['uuid-abc' => $entity], $checker);

        $this->assertSame(['uuid-abc' => $entity], $result);
    }
}

final class FilterAuthorizedEntitiesHarness
{
    use SkipsUnauthorizedEntitiesTrait;

    /**
     * @param array<int|string, object> $entitiesByKey
     * @return array<int|string, object>
     */
    public function filter(
        string $attribute,
        array $entitiesByKey,
        ObjectAuthorizationChecker $objectAuthChecker,
    ): array {
        return $this->filterAuthorizedEntities($objectAuthChecker, $attribute, $entitiesByKey);
    }
}
