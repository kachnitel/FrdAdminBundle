<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Security;

use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Kachnitel\AdminBundle\Security\SkipsUnauthorizedEntitiesTrait;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covers SkipsUnauthorizedEntitiesTrait::filterAuthorizedEntities() — the
 * shared skip-on-deny loop used by EntityListBatchService::batchDelete()
 * and ArchiveButton::execute().
 *
 * Exercised through a minimal fixture class (below) rather than directly,
 * since PHP traits can't be instantiated. Every test here mocks
 * ObjectAuthorizationChecker the conventional way — createMock() plus only
 * an isGranted() stub — the exact pattern that broke when this logic
 * briefly lived as ObjectAuthorizationChecker::filterGranted() instead of
 * here: createMock() replaces every public method, so an unstubbed
 * filterGranted() silently returned [] regardless of what was passed in.
 * Routing the loop through isGranted() only, via this trait, means any
 * consumer's test that mocks the checker this way — including every
 * pre-existing test in this bundle — keeps working without needing to know
 * this trait exists.
 *
 * @see \Kachnitel\AdminBundle\Tests\Unit\Service\EntityListBatchServiceTest
 * @see \Kachnitel\AdminBundle\Tests\Twig\Components\AdminAction\ArchiveButtonTest
 */
#[PHPUnit\CoversTrait(SkipsUnauthorizedEntitiesTrait::class)]
#[PHPUnit\Group('security')]
#[PHPUnit\Group('object-authorization')]
#[PHPUnit\AllowMockObjectsWithoutExpectations]
final class SkipsUnauthorizedEntitiesTraitTest extends TestCase
{
    /** @var ObjectAuthorizationChecker&MockObject */
    private ObjectAuthorizationChecker $objectAuthChecker;

    private SkipsUnauthorizedEntitiesTraitFixture $fixture;

    protected function setUp(): void
    {
        $this->objectAuthChecker = $this->createMock(ObjectAuthorizationChecker::class);
        $this->fixture = new SkipsUnauthorizedEntitiesTraitFixture();
    }

    #[PHPUnit\Test]
    public function returnsOnlyGrantedEntitiesPreservingTheirOriginalKeys(): void
    {
        $allowed = new \stdClass();
        $denied  = new \stdClass();

        $this->objectAuthChecker->method('isGranted')->willReturnMap([
            ['ADMIN_DELETE', $allowed, true],
            ['ADMIN_DELETE', $denied, false],
        ]);

        $result = $this->fixture->callFilter($this->objectAuthChecker, 'ADMIN_DELETE', [
            7  => $allowed,
            12 => $denied,
        ]);

        $this->assertSame([7 => $allowed], $result);
    }

    #[PHPUnit\Test]
    public function returnsEverythingWhenIsGrantedAlwaysTrue(): void
    {
        $entityA = new \stdClass();
        $entityB = new \stdClass();

        $this->objectAuthChecker->method('isGranted')->willReturn(true);

        $result = $this->fixture->callFilter($this->objectAuthChecker, 'ADMIN_DELETE', [
            1 => $entityA,
            2 => $entityB,
        ]);

        $this->assertSame([1 => $entityA, 2 => $entityB], $result);
    }

    #[PHPUnit\Test]
    public function returnsEmptyArrayWhenEverythingIsDenied(): void
    {
        $this->objectAuthChecker->method('isGranted')->willReturn(false);

        $result = $this->fixture->callFilter($this->objectAuthChecker, 'ADMIN_ARCHIVE', [
            1 => new \stdClass(),
            2 => new \stdClass(),
        ]);

        $this->assertSame([], $result);
    }

    #[PHPUnit\Test]
    public function returnsEmptyArrayForEmptyInputWithoutConsultingTheChecker(): void
    {
        $this->objectAuthChecker->expects($this->never())->method('isGranted');

        $this->assertSame([], $this->fixture->callFilter($this->objectAuthChecker, 'ADMIN_DELETE', []));
    }

    #[PHPUnit\Test]
    public function preservesStringKeysAsWellAsIntKeys(): void
    {
        $entity = new \stdClass();

        $this->objectAuthChecker->method('isGranted')->willReturn(true);

        $result = $this->fixture->callFilter($this->objectAuthChecker, 'ADMIN_EDIT', ['uuid-abc' => $entity]);

        $this->assertSame(['uuid-abc' => $entity], $result);
    }

    /**
     * Only isGranted() is called — never any other method on the checker —
     * confirming the trait's whole reason for existing: it must not depend
     * on a method beyond the one every consumer's test already mocks.
     */
    #[PHPUnit\Test]
    public function neverCallsAnyMethodOtherThanIsGranted(): void
    {
        $objectAuthChecker = $this->getMockBuilder(ObjectAuthorizationChecker::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isGranted', 'isEnabledFor', 'denyAccessUnlessGranted'])
            ->getMock();
        $objectAuthChecker->method('isGranted')->willReturn(true);
        $objectAuthChecker->expects($this->never())->method('isEnabledFor');
        $objectAuthChecker->expects($this->never())->method('denyAccessUnlessGranted');

        $this->fixture->callFilter($objectAuthChecker, 'ADMIN_DELETE', [1 => new \stdClass()]);
    }
}

/**
 * Minimal fixture exposing the trait's private method for direct testing.
 * Traits can't be instantiated or tested standalone in PHP.
 */
final class SkipsUnauthorizedEntitiesTraitFixture
{
    use SkipsUnauthorizedEntitiesTrait;

    /**
     * @param array<array-key, object> $entitiesByKey
     * @return array<array-key, object>
     */
    public function callFilter(
        ObjectAuthorizationChecker $objectAuthChecker,
        string $attribute,
        array $entitiesByKey,
    ): array {
        return $this->filterAuthorizedEntities($objectAuthChecker, $attribute, $entitiesByKey);
    }
}
