<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Components;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Twig\Components\InlineEntityForm;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * Characterization tests for InlineEntityForm::resolveEntityLabel(), covering
 * the private tryGetterLabel() / tryToStringLabel() / fallbackIdLabel() split
 * introduced to bring resolveEntityLabel() under PHPMD's CyclomaticComplexity
 * threshold (10). Behaviour is unchanged from the pre-split single method.
 *
 * Priority order under test: getLabel() -> getName() -> getTitle() -> __toString() -> #id
 *
 * resolveEntityLabel() stays private; PHP 8.1+ allows ReflectionMethod::invoke()
 * on private methods without setAccessible(true), so no visibility change or
 * testable-subclass wrapper is needed.
 */
#[CoversClass(InlineEntityForm::class)]
#[Group('inline-add')]
#[AllowMockObjectsWithoutExpectations]
final class InlineEntityFormResolveEntityLabelTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
    }

    // ── getLabel() / getName() / getTitle() priority ──────────────────────────

    #[Test]
    public function prefersGetLabelOverEverythingElse(): void
    {
        $entity = new class {
            public function getLabel(): string { return 'From label'; }
            public function getName(): string { return 'From name'; }
            public function getTitle(): string { return 'From title'; }
            public function __toString(): string { return 'From toString'; }
        };

        $this->assertSame('From label', $this->resolveLabel($entity));
    }

    #[Test]
    public function fallsBackToGetNameWhenNoGetLabel(): void
    {
        $entity = new class {
            public function getName(): string { return 'From name'; }
            public function getTitle(): string { return 'From title'; }
        };

        $this->assertSame('From name', $this->resolveLabel($entity));
    }

    #[Test]
    public function fallsBackToGetTitleWhenNoGetLabelOrGetName(): void
    {
        $entity = new class {
            public function getTitle(): string { return 'From title'; }
        };

        $this->assertSame('From title', $this->resolveLabel($entity));
    }

    #[Test]
    public function skipsGetterThatReturnsEmptyString(): void
    {
        $entity = new class {
            public function getLabel(): string { return ''; }
            public function getName(): string { return 'From name'; }
        };

        $this->assertSame('From name', $this->resolveLabel($entity));
    }

    #[Test]
    public function skipsGetterThatReturnsNonString(): void
    {
        $entity = new class {
            public function getLabel(): ?string { return null; } // @phpstan-ignore return.unusedType
            public function getName(): string { return 'From name'; }
        };

        $this->assertSame('From name', $this->resolveLabel($entity));
    }

    #[Test]
    public function skipsGetterThatThrows(): void
    {
        $entity = new class {
            public function getLabel(): string { throw new \RuntimeException('boom'); }
            public function getName(): string { return 'From name'; }
        };

        $this->assertSame('From name', $this->resolveLabel($entity));
    }

    // ── __toString() fallback ──────────────────────────────────────────────────

    #[Test]
    public function fallsBackToToStringWhenNoLabelGetters(): void
    {
        $entity = new class {
            public function __toString(): string { return 'From toString'; }
        };

        $this->assertSame('From toString', $this->resolveLabel($entity));
    }

    #[Test]
    public function skipsToStringWhenItReturnsEmptyString(): void
    {
        $entity = new class {
            public function __toString(): string { return ''; }
            public function getId(): int { return 9; }
        };

        $this->em->method('getClassMetadata')->willReturn($this->makeMetadataStub(['id' => 9]));

        $this->assertSame('#9', $this->resolveLabel($entity));
    }

    #[Test]
    public function skipsToStringWhenItThrows(): void
    {
        $entity = new class {
            public function __toString(): string { throw new \RuntimeException('boom'); }
            public function getId(): int { return 3; }
        };

        $this->em->method('getClassMetadata')->willReturn($this->makeMetadataStub(['id' => 3]));

        $this->assertSame('#3', $this->resolveLabel($entity));
    }

    // ── #id fallback ────────────────────────────────────────────────────────────

    #[Test]
    public function fallsBackToIdWhenNoLabelGettersOrToString(): void
    {
        $entity = new class {
            public function getId(): int { return 42; }
        };

        $this->em->method('getClassMetadata')->willReturn($this->makeMetadataStub(['id' => 42]));

        $this->assertSame('#42', $this->resolveLabel($entity));
    }

    #[Test]
    public function fallsBackToQuestionMarkWhenIdentifierValuesAreEmpty(): void
    {
        $entity = new class {};

        $this->em->method('getClassMetadata')->willReturn($this->makeMetadataStub([]));

        $this->assertSame('#?', $this->resolveLabel($entity));
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function resolveLabel(object $entity): string
    {
        $form = new InlineEntityForm($this->em, $this->createStub(FormFactoryInterface::class));

        $method = new \ReflectionMethod($form, 'resolveEntityLabel');

        /** @var string $result */
        $result = $method->invoke($form, $entity);

        return $result;
    }

    /**
     * @param array<string, mixed> $identifierValues
     * @return ClassMetadata<object>&MockObject
     */
    private function makeMetadataStub(array $identifierValues): ClassMetadata
    {
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getIdentifierValues')->willReturn($identifierValues);

        return $metadata;
    }
}
