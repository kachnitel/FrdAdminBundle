<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DataSource;

use Kachnitel\AdminBundle\Attribute\AdminCustomColumn;
use Kachnitel\DataSourceContracts\ColumnMetadata;
use Kachnitel\AdminBundle\DataSource\DoctrineCustomColumnProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @group custom-columns
 */
final class DoctrineCustomColumnProviderTest extends TestCase
{
    private DoctrineCustomColumnProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new DoctrineCustomColumnProvider();
    }

    #[Test]
    public function returnsEmptyArrayForClassWithNoCustomColumns(): void
    {
        $result = $this->provider->getCustomColumns(NoCustomColumnsEntity::class);

        $this->assertSame([], $result);
    }

    #[Test]
    public function returnsSingleCustomColumnFromAttribute(): void
    {
        $result = $this->provider->getCustomColumns(SingleCustomColumnEntity::class);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('fullName', $result);

        $meta = $result['fullName'];
        $this->assertInstanceOf(ColumnMetadata::class, $meta); // @phpstan-ignore method.alreadyNarrowedType
        $this->assertSame('fullName', $meta->name);
        $this->assertSame('admin/columns/full_name.html.twig', $meta->template);
        $this->assertSame('custom', $meta->type);
        $this->assertFalse($meta->sortable);
    }

    #[Test]
    public function humanisesLabelFromNameWhenLabelIsNull(): void
    {
        $result = $this->provider->getCustomColumns(SingleCustomColumnEntity::class);

        // 'fullName' → 'Full name'
        $this->assertSame('Full name', $result['fullName']->label);
    }

    #[Test]
    public function usesExplicitLabelWhenProvided(): void
    {
        $result = $this->provider->getCustomColumns(LabelledCustomColumnEntity::class);

        $this->assertSame('Full Name', $result['fullName']->label);
    }

    #[Test]
    public function respectsSortableFlag(): void
    {
        $result = $this->provider->getCustomColumns(SortableCustomColumnEntity::class);

        $this->assertTrue($result['score']->sortable);
    }

    #[Test]
    public function returnsMultipleCustomColumnsInDeclarationOrder(): void
    {
        $result = $this->provider->getCustomColumns(MultipleCustomColumnsEntity::class);

        $this->assertCount(2, $result);
        $keys = array_keys($result);
        $this->assertSame('fullName', $keys[0]);
        $this->assertSame('activityBadge', $keys[1]);
    }

    #[Test]
    public function columnTypeIsAlwaysCustom(): void
    {
        $result = $this->provider->getCustomColumns(SingleCustomColumnEntity::class);

        $this->assertSame('custom', $result['fullName']->type);
    }

    #[Test]
    public function templateIsPreservedVerbatim(): void
    {
        $result = $this->provider->getCustomColumns(MultipleCustomColumnsEntity::class);

        $this->assertSame('admin/columns/full_name.html.twig', $result['fullName']->template);
        $this->assertSame('admin/columns/activity.html.twig', $result['activityBadge']->template);
    }
}

// ---------------------------------------------------------------------------
// Inline fixture classes (no Doctrine mapping needed — provider only reads
// the AdminCustomColumn attribute via reflection)
// ---------------------------------------------------------------------------

class NoCustomColumnsEntity {}

#[AdminCustomColumn(
    name: 'fullName',
    template: 'admin/columns/full_name.html.twig',
)]
class SingleCustomColumnEntity {}

#[AdminCustomColumn(
    name: 'fullName',
    template: 'admin/columns/full_name.html.twig',
    label: 'Full Name',
)]
class LabelledCustomColumnEntity {}

#[AdminCustomColumn(
    name: 'score',
    template: 'admin/columns/score.html.twig',
    sortable: true,
)]
class SortableCustomColumnEntity {}

#[AdminCustomColumn(
    name: 'fullName',
    template: 'admin/columns/full_name.html.twig',
)]
#[AdminCustomColumn(
    name: 'activityBadge',
    template: 'admin/columns/activity.html.twig',
    label: 'Activity',
)]
class MultipleCustomColumnsEntity {}
