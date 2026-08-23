<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\RowAction;

use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;

#[Group('row-action')]
#[AllowMockObjectsWithoutExpectations]
final class RowActionExpressionLanguageTest extends TestCase
{
    /** @var AuthorizationCheckerInterface&MockObject */
    private AuthorizationCheckerInterface $authChecker;

    protected function setUp(): void
    {
        $this->authChecker = $this->createMock(AuthorizationCheckerInterface::class);
    }

    /**
     * Entity with PUBLIC properties — original test helper, kept for baseline coverage.
     */
    private function entity(mixed $status = 'pending', bool $active = true, int $stock = 10): object
    {
        return new class ($status, $active, $stock) {
            public function __construct(
                public readonly mixed $status,
                public readonly bool $active,
                public readonly int $stock,
            ) {}

            public function getStatus(): mixed { return $this->status; }
            public function isActive(): bool { return $this->active; }
            public function getStock(): int { return $this->stock; }
        };
    }

    #[Test]
    public function methodCallSyntaxWorks(): void
    {
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());
        $entity = $this->entity(status: 'pending');

        // Symfony's expression language supports calling entity methods directly.
        $this->assertTrue($lang->evaluate('entity.getStatus() == "pending"', $entity));
    }

    // -------------------------------------------------------------------------
    // Simple property expressions (public properties — baseline)
    // -------------------------------------------------------------------------

    #[Test]
    public function equalityCheckReturnsTrueWhenMatch(): void
    {
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());
        $entity = $this->entity(status: 'pending');

        $this->assertTrue($lang->evaluate('entity.status == "pending"', $entity));
    }

    #[Test]
    public function equalityCheckReturnsFalseWhenNoMatch(): void
    {
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());
        $entity = $this->entity(status: 'archived');

        $this->assertFalse($lang->evaluate('entity.status == "pending"', $entity));
    }

    #[Test]
    public function inequalityCheck(): void
    {
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());
        $entity = $this->entity(status: 'archived');

        $this->assertFalse($lang->evaluate('entity.status != "archived"', $entity));
    }

    #[Test]
    public function booleanPropertyCheck(): void
    {
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());

        $this->assertTrue($lang->evaluate('entity.active', $this->entity(active: true)));
        $this->assertFalse($lang->evaluate('entity.active', $this->entity(active: false)));
    }

    #[Test]
    public function negationWithNotOperator(): void
    {
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());

        $this->assertTrue($lang->evaluate('not entity.active', $this->entity(active: false)));
        $this->assertFalse($lang->evaluate('not entity.active', $this->entity(active: true)));
    }

    #[Test]
    public function exclamationNegation(): void
    {
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());

        $this->assertTrue($lang->evaluate('!entity.active', $this->entity(active: false)));
        $this->assertFalse($lang->evaluate('!entity.active', $this->entity(active: true)));
    }

    #[Test]
    public function numericGreaterThanCheck(): void
    {
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());

        $this->assertTrue($lang->evaluate('entity.stock > 0', $this->entity(stock: 5)));
        $this->assertFalse($lang->evaluate('entity.stock > 0', $this->entity(stock: 0)));
    }

    #[Test]
    public function itemPrefixWorksAsAliasForEntity(): void
    {
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());
        $entity = $this->entity(status: 'pending');

        $this->assertTrue($lang->evaluate('item.status == "pending"', $entity));
    }

    // -------------------------------------------------------------------------
    // Combining conditions (&&, ||, and, or)
    // -------------------------------------------------------------------------

    #[Test]
    public function andOperatorRequiresBothTrue(): void
    {
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());
        $entity = $this->entity(status: 'pending', active: true);

        $this->assertTrue($lang->evaluate('entity.status == "pending" && entity.active', $entity));
        $this->assertFalse($lang->evaluate('entity.status == "pending" && !entity.active', $entity));
    }

    #[Test]
    public function orOperatorRequiresAtLeastOneTrue(): void
    {
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());
        $entity = $this->entity(status: 'archived', active: true);

        $this->assertTrue($lang->evaluate('entity.status == "pending" || entity.active', $entity));
        $this->assertFalse($lang->evaluate('entity.status == "pending" || !entity.active', $entity));
    }

    #[Test]
    public function complexCombinedExpression(): void
    {
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());
        $entity = $this->entity(status: 'pending', stock: 5);

        $this->assertTrue(
            $lang->evaluate(
                'entity.status == "pending" && entity.stock > 0',
                $entity,
            )
        );
    }

    // -------------------------------------------------------------------------
    // is_granted() function
    // -------------------------------------------------------------------------

    #[Test]
    public function isGrantedReturnsTrueWhenRoleGranted(): void
    {
        $this->authChecker->expects($this->once())->method('isGranted')->with('ROLE_ADMIN', null)->willReturn(true);
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());
        $entity = $this->entity();

        $this->assertTrue($lang->evaluate('is_granted("ROLE_ADMIN")', $entity, $this->authChecker));
    }

    #[Test]
    public function isGrantedReturnsFalseWhenRoleNotGranted(): void
    {
        $this->authChecker->expects($this->once())->method('isGranted')->with('ROLE_SUPER_ADMIN', null)->willReturn(false);
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());
        $entity = $this->entity();

        $this->assertFalse($lang->evaluate('is_granted("ROLE_SUPER_ADMIN")', $entity, $this->authChecker));
    }

    #[Test]
    public function isGrantedReturnsFalseWhenAuthCheckerNotProvided(): void
    {
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());
        $entity = $this->entity();

        $this->assertFalse($lang->evaluate('is_granted("ROLE_ADMIN")', $entity));
    }

    #[Test]
    public function isGrantedCombinedWithPropertyCondition(): void
    {
        $this->authChecker->expects($this->once())->method('isGranted')->with('ROLE_EDITOR', null)->willReturn(true);
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());
        $entity = $this->entity(status: 'pending');

        $this->assertTrue(
            $lang->evaluate(
                'entity.status == "pending" && is_granted("ROLE_EDITOR")',
                $entity,
                $this->authChecker,
            )
        );
    }

    #[Test]
    public function isGrantedCombinedWithPropertyConditionFalseWhenRoleMissing(): void
    {
        $this->authChecker->expects($this->once())->method('isGranted')->with('ROLE_EDITOR', null)->willReturn(false);
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());
        $entity = $this->entity(status: 'pending');

        $this->assertFalse(
            $lang->evaluate(
                'entity.status == "pending" && is_granted("ROLE_EDITOR")',
                $entity,
                $this->authChecker,
            )
        );
    }

    #[Test]
    public function isGrantedWithSingleQuotes(): void
    {
        $this->authChecker->expects($this->once())->method('isGranted')->with('ROLE_ADMIN', null)->willReturn(true);
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());

        $this->assertTrue($lang->evaluate("is_granted('ROLE_ADMIN')", $this->entity(), $this->authChecker));
    }

    // -------------------------------------------------------------------------
    // Error / safe-default behaviour
    // -------------------------------------------------------------------------

    #[Test]
    public function invalidExpressionReturnsFalse(): void
    {
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());
        $entity = $this->entity();

        // Non-existent property — PropertyAccess throws, proxy re-throws, evaluator catches
        $this->assertFalse($lang->evaluate('entity.nonExistentProperty == true', $entity));
    }

    #[Test]
    public function malformedExpressionReturnsFalse(): void
    {
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());
        $entity = $this->entity();

        $this->assertFalse($lang->evaluate('&&& invalid expression', $entity));
    }

    #[Test]
    public function nullComparisonReturnsTrueWhenNull(): void
    {
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());

        $entity = new class () {
            public ?string $verifiedAt = null;
            public function getVerifiedAt(): ?string { return $this->verifiedAt; }
        };

        $this->assertTrue($lang->evaluate('entity.verifiedAt == null', $entity));
    }

    #[Test]
    public function nullComparisonReturnsFalseWhenNotNull(): void
    {
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());

        $entity = new class () {
            public ?string $verifiedAt = '2024-01-01';
            public function getVerifiedAt(): ?string { return $this->verifiedAt; }
        };

        $this->assertFalse($lang->evaluate('entity.verifiedAt == null', $entity));
    }

    #[Test]
    public function notNullComparisonReturnsTrueWhenNotNull(): void
    {
        $lang = new RowActionExpressionLanguage(new ExpressionLanguage());

        $entity = new class () {
            public ?string $verifiedAt = '2024-01-01';
            public function getVerifiedAt(): ?string { return $this->verifiedAt; }
        };

        $this->assertTrue($lang->evaluate('entity.verifiedAt != null', $entity));
    }
}
