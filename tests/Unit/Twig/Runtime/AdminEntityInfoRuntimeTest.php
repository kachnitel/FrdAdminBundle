<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Tests\Fixtures\InlineEditEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Twig\Runtime\AdminEntityInfoRuntime;
use Kachnitel\AdminBundle\Utils\ObjectHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Kachnitel\AdminBundle\Twig\Runtime\AdminEntityInfoRuntime::getColumnAttribute
 * @covers \Kachnitel\AdminBundle\Attribute\AdminColumn
 * @group entity-info
 */
#[CoversClass(AdminEntityInfoRuntime::class)]
#[UsesClass(AdminColumn::class)]
#[UsesClass(AttributeHelper::class)]
#[UsesClass(ObjectHelper::class)]
class AdminEntityInfoRuntimeTest extends TestCase
{
    private AdminEntityInfoRuntime $runtime;
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->runtime = new AdminEntityInfoRuntime(
            new AttributeHelper(),
            $this->em,
        );
    }

    // ── getColumnAttribute ────────────────────────────────────────────────────

    #[Test]
    public function returnsNullWhenPropertyDoesNotExist(): void
    {
        $this->assertNull($this->runtime->getColumnAttribute(new TestEntity(), 'nonExistentProperty'));
    }

    #[Test]
    public function returnsNullWhenPropertyHasNoAdminColumnAttribute(): void
    {
        // TestEntity::$name has #[ColumnFilter] but no #[AdminColumn]
        $this->assertNull($this->runtime->getColumnAttribute(new TestEntity(), 'name'));
    }

    #[Test]
    public function returnsAttributeInstanceWhenPresent(): void
    {
        // InlineEditEntity::$title has #[AdminColumn(editable: true)]
        $attr = $this->runtime->getColumnAttribute(new InlineEditEntity(), 'title');

        $this->assertInstanceOf(AdminColumn::class, $attr);
        $this->assertTrue($attr->editable);
    }

    // ── AdminColumn defaults ──────────────────────────────────────────────────

    #[Test]
    public function collectionDisplayDefaultsToFalse(): void
    {
        $attr = new AdminColumn();

        $this->assertFalse($attr->collectionDisplay);
    }

    #[Test]
    public function collectionCollapsibleDefaultsToTrue(): void
    {
        $attr = new AdminColumn(collectionDisplay: true);

        $this->assertTrue($attr->collectionCollapsible);
    }

    #[Test]
    public function collectionLimitDefaultsToFive(): void
    {
        $attr = new AdminColumn(collectionDisplay: true);

        $this->assertSame(5, $attr->collectionLimit);
    }

    // ── collectionLimit edge cases ────────────────────────────────────────────

    #[Test]
    public function collectionLimitNullMeansShowAll(): void
    {
        $attr = new AdminColumn(collectionDisplay: true, collectionLimit: null);

        $this->assertNull($attr->collectionLimit);
    }

    #[Test]
    public function collectionLimitZeroMeansShowAll(): void
    {
        $attr = new AdminColumn(collectionDisplay: true, collectionLimit: 0);

        $this->assertSame(0, $attr->collectionLimit);
    }

    #[Test]
    public function explicitPositiveLimitIsPreserved(): void
    {
        $attr = new AdminColumn(collectionDisplay: true, collectionLimit: 10);

        $this->assertSame(10, $attr->collectionLimit);
    }

    // ── collectionCollapsible ─────────────────────────────────────────────────

    #[Test]
    public function collectionCanBeSetToNonCollapsible(): void
    {
        $attr = new AdminColumn(collectionDisplay: true, collectionCollapsible: false);

        $this->assertFalse($attr->collectionCollapsible);
    }

    // ── collectionDisplay disabled stays backward compatible ──────────────────

    #[Test]
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
