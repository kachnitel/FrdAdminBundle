<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Utils;

use Kachnitel\AdminBundle\Utils\EntityHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers EntityHelper::getEntityLabel() and, through it, stringifyEntityValue().
 *
 * Priority under test:
 *   label (getter, then public prop) -> name -> title -> __toString() -> #getId() -> #id prop -> short class name
 */
#[CoversClass(EntityHelper::class)]
#[Group('entity-helper')]
final class EntityHelperTest extends TestCase
{
    // ── getLabel() / getName() / getTitle() getters ───────────────────────────

    #[Test]
    public function prefersGetLabelOverEverythingElse(): void
    {
        $entity = new class {
            public function getLabel(): string { return 'From label'; }
            public function getName(): string { return 'From name'; }
            public function getTitle(): string { return 'From title'; }
            public function __toString(): string { return 'From toString'; }
            public function getId(): int { return 1; }
        };

        $this->assertSame('From label', EntityHelper::getEntityLabel($entity));
    }

    #[Test]
    public function fallsBackToGetNameWhenNoGetLabel(): void
    {
        $entity = new class {
            public function getName(): string { return 'From name'; }
            public function getTitle(): string { return 'From title'; }
        };

        $this->assertSame('From name', EntityHelper::getEntityLabel($entity));
    }

    #[Test]
    public function fallsBackToGetTitleWhenNoGetLabelOrGetName(): void
    {
        $entity = new class {
            public function getTitle(): string { return 'From title'; }
        };

        $this->assertSame('From title', EntityHelper::getEntityLabel($entity));
    }

    // ── Public property variants ──────────────────────────────────────────────

    #[Test]
    public function usesPublicLabelPropertyWhenNoGetter(): void
    {
        $entity = new class {
            public string $label = 'Prop label';
        };

        $this->assertSame('Prop label', EntityHelper::getEntityLabel($entity));
    }

    #[Test]
    public function usesPublicNameAndTitlePropertiesInOrder(): void
    {
        $nameAndTitle = new class {
            public string $title = 'Prop title';
            public string $name = 'Prop name';
        };
        $titleOnly = new class {
            public string $title = 'Prop title';
        };

        $this->assertSame('Prop name', EntityHelper::getEntityLabel($nameAndTitle));
        $this->assertSame('Prop title', EntityHelper::getEntityLabel($titleOnly));
    }

    #[Test]
    public function getterForEarlierPropertyBeatsPublicPropertyOfLaterOne(): void
    {
        $entity = new class {
            public string $label = 'Prop label';
            public function getLabel(): string { return 'Getter label'; }
        };

        $this->assertSame('Getter label', EntityHelper::getEntityLabel($entity));
    }

    #[Test]
    public function publicLabelPropertyBeatsGetNameGetter(): void
    {
        $entity = new class {
            public string $label = 'Prop label';
            public function getName(): string { return 'Getter name'; }
        };

        $this->assertSame('Prop label', EntityHelper::getEntityLabel($entity));
    }

    #[Test]
    public function nullPublicPropertyIsSkipped(): void
    {
        $entity = new class {
            public ?string $label = null;
            public string $name = 'Prop name';
        };

        $this->assertSame('Prop name', EntityHelper::getEntityLabel($entity));
    }

    // ── stringifyEntityValue() behaviour via getters ──────────────────────────

    #[Test]
    public function scalarGetterValuesAreCastToString(): void
    {
        $int = new class {
            public function getLabel(): int { return 5; }
        };
        $bool = new class {
            public function getLabel(): bool { return true; }
        };

        $this->assertSame('5', EntityHelper::getEntityLabel($int));
        $this->assertSame('1', EntityHelper::getEntityLabel($bool));
    }

    #[Test]
    public function stringableGetterValueIsCastToString(): void
    {
        $entity = new class {
            public function getName(): object
            {
                return new class {
                    public function __toString(): string { return 'Stringable name'; }
                };
            }
        };

        $this->assertSame('Stringable name', EntityHelper::getEntityLabel($entity));
    }

    #[Test]
    public function existingGetterReturningNullYieldsEmptyStringWithoutFallingThrough(): void
    {
        // Characterization: an existing getter short-circuits even when its value is unusable.
        $entity = new class {
            public function getLabel(): ?string { return null; } // @phpstan-ignore return.unusedType
            public function getName(): string { return 'From name'; }
        };

        $this->assertSame('', EntityHelper::getEntityLabel($entity));
    }

    #[Test]
    public function nonStringableObjectGetterValueYieldsEmptyString(): void
    {
        $entity = new class {
            public function getLabel(): object { return new \stdClass(); }
        };

        $this->assertSame('', EntityHelper::getEntityLabel($entity));
    }

    // ── __toString() fallback ─────────────────────────────────────────────────

    #[Test]
    public function fallsBackToToStringWhenNoLabelGettersOrProperties(): void
    {
        $entity = new class {
            public function __toString(): string { return 'From toString'; }
            public function getId(): int { return 9; }
        };

        $this->assertSame('From toString', EntityHelper::getEntityLabel($entity));
    }

    // ── Id fallbacks ──────────────────────────────────────────────────────────

    #[Test]
    public function fallsBackToHashGetIdWhenNothingElseMatches(): void
    {
        $entity = new class {
            public function getId(): int { return 42; }
        };

        $this->assertSame('#42', EntityHelper::getEntityLabel($entity));
    }

    #[Test]
    public function getIdWithNonScalarValueYieldsBareHash(): void
    {
        $entity = new class {
            public function getId(): ?int { return null; } // @phpstan-ignore return.unusedType
        };

        $this->assertSame('#', EntityHelper::getEntityLabel($entity));
    }

    #[Test]
    public function fallsBackToHashPublicIdPropertyWhenNoGetId(): void
    {
        $entity = new class {
            public int $id = 7;
        };

        $this->assertSame('#7', EntityHelper::getEntityLabel($entity));
    }

    #[Test]
    public function getIdBeatsPublicIdProperty(): void
    {
        $entity = new class {
            public int $id = 7;
            public function getId(): int { return 8; }
        };

        $this->assertSame('#8', EntityHelper::getEntityLabel($entity));
    }

    // ── Last resort ───────────────────────────────────────────────────────────

    #[Test]
    public function fallsBackToShortClassNameWhenNothingIsAvailable(): void
    {
        $this->assertSame('EntityHelperBareFixture', EntityHelper::getEntityLabel(new EntityHelperBareFixture()));
    }
}

/** Named (non-anonymous) fixture so getShortName() is predictable. */
final class EntityHelperBareFixture {}
