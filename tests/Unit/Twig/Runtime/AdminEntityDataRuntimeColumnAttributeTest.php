<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Tests\Fixtures\InlineEditEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Twig\Runtime\ActionAccessibilityChecker;
use Kachnitel\AdminBundle\Twig\Runtime\AdminEntityDataRuntime;
use Kachnitel\AdminBundle\Twig\Runtime\AdminRouteRuntime;
use Kachnitel\AdminBundle\Utils\ObjectHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Kachnitel\AdminBundle\Twig\Runtime\AdminEntityDataRuntime::getColumnAttribute
 * @covers \Kachnitel\AdminBundle\Attribute\AdminColumn
 * @group collection-display
 */
#[CoversClass(AdminEntityDataRuntime::class)]
#[UsesClass(ActionAccessibilityChecker::class)]
#[UsesClass(AdminRouteRuntime::class)]
#[UsesClass(AdminColumn::class)]
#[UsesClass(AttributeHelper::class)]
#[UsesClass(ObjectHelper::class)]
class AdminEntityDataRuntimeColumnAttributeTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    private AdminEntityDataRuntime $runtime;

    protected function setUp(): void
    {
        $this->em      = $this->createMock(EntityManagerInterface::class);
        $this->runtime = new AdminEntityDataRuntime($this->em, new AttributeHelper());
    }

    // ── getColumnAttribute ────────────────────────────────────────────────────

    /** @test */
    public function returnsNullWhenPropertyDoesNotExist(): void
    {
        $this->assertNull($this->runtime->getColumnAttribute(new TestEntity(), 'nonExistentProperty'));
    }

    /** @test */
    public function returnsNullWhenPropertyHasNoAdminColumnAttribute(): void
    {
        // TestEntity::$name has #[ColumnFilter] but no #[AdminColumn]
        $this->assertNull($this->runtime->getColumnAttribute(new TestEntity(), 'name'));
    }

    /** @test */
    public function returnsAttributeInstanceWhenPresent(): void
    {
        // InlineEditEntity::$title has #[AdminColumn(editable: true)]
        $attr = $this->runtime->getColumnAttribute(new InlineEditEntity(), 'title');

        $this->assertInstanceOf(AdminColumn::class, $attr);
        $this->assertTrue($attr->editable);
    }

    // ── AdminColumn defaults ──────────────────────────────────────────────────

    /** @test */
    public function collectionDisplayDefaultsToFalse(): void
    {
        $attr = new AdminColumn();

        $this->assertFalse($attr->collectionDisplay);
    }

    /** @test */
    public function collectionCollapsibleDefaultsToTrue(): void
    {
        $attr = new AdminColumn(collectionDisplay: true);

        $this->assertTrue($attr->collectionCollapsible);
    }

    /** @test */
    public function collectionLimitDefaultsToFive(): void
    {
        $attr = new AdminColumn(collectionDisplay: true);

        $this->assertSame(5, $attr->collectionLimit);
    }

    // ── collectionLimit edge cases ────────────────────────────────────────────

    /** @test */
    public function collectionLimitNullMeansShowAll(): void
    {
        $attr = new AdminColumn(collectionDisplay: true, collectionLimit: null);

        $this->assertNull($attr->collectionLimit);
    }

    /** @test */
    public function collectionLimitZeroMeansShowAll(): void
    {
        $attr = new AdminColumn(collectionDisplay: true, collectionLimit: 0);

        $this->assertSame(0, $attr->collectionLimit);
    }

    /** @test */
    public function explicitPositiveLimitIsPreserved(): void
    {
        $attr = new AdminColumn(collectionDisplay: true, collectionLimit: 10);

        $this->assertSame(10, $attr->collectionLimit);
    }

    // ── collectionCollapsible ─────────────────────────────────────────────────

    /** @test */
    public function collectionCanBeSetToNonCollapsible(): void
    {
        $attr = new AdminColumn(collectionDisplay: true, collectionCollapsible: false);

        $this->assertFalse($attr->collectionCollapsible);
    }

    // ── collectionDisplay disabled stays backward compatible ──────────────────

    /** @test */
    public function collectionDisplayFalseDoesNotAffectOtherDefaults(): void
    {
        $attr = new AdminColumn();

        $this->assertFalse($attr->collectionDisplay);
        $this->assertTrue($attr->collectionCollapsible);
        $this->assertSame(5, $attr->collectionLimit);
        $this->assertNull($attr->editable);
        $this->assertNull($attr->group);
    }
}
