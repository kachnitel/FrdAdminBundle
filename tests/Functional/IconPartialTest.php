<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\ValueObject\BatchAction;
use Kachnitel\AdminBundle\ValueObject\RowAction;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * The icon partial is the single place where an action/entity icon is rendered.
 * Applications customise icon output by overriding it in
 * templates/bundles/KachnitelAdminBundle/components/_Icon.html.twig.
 */
#[Group('icons')]
final class IconPartialTest extends KernelTestCase
{
    private const PARTIAL = '@KachnitelAdmin/components/_Icon.html.twig';

    protected static function getKernelClass(): string
    {
        return ComponentTestKernel::class;
    }

    private function twig(): Environment
    {
        self::bootKernel();
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig;
    }

    public function testPartialOutputsEmojiVerbatimWithoutWhitespace(): void
    {
        $this->assertSame('✅', $this->twig()->render(self::PARTIAL, ['icon' => '✅']));
    }

    public function testPartialEscapesHtml(): void
    {
        $this->assertSame('&lt;b&gt;', $this->twig()->render(self::PARTIAL, ['icon' => '<b>']));
    }

    public function testRowActionLinkRendersIconThroughPartial(): void
    {
        $html = $this->twig()->render('@KachnitelAdmin/components/EntityList/_RowActionButton.html.twig', [
            'action' => new RowAction(name: 'approve', label: 'Approve', icon: '✅', url: '/approve'),
            'entity' => new TestEntity(),
            'entityShortClass' => 'TestEntity',
        ]);

        $this->assertMatchesRegularExpression('/<a [^>]*>\s*✅\s*<\/a>/u', $html);
    }

    public function testRowActionWithoutIconFallsBackToLabel(): void
    {
        $html = $this->twig()->render('@KachnitelAdmin/components/EntityList/_RowActionButton.html.twig', [
            'action' => new RowAction(name: 'approve', label: 'Approve', url: '/approve'),
            'entity' => new TestEntity(),
            'entityShortClass' => 'TestEntity',
        ]);

        $this->assertMatchesRegularExpression('/<a [^>]*>\s*Approve\s*<\/a>/u', $html);
    }

    public function testBatchActionButtonRendersIconBeforeLabel(): void
    {
        $html = $this->twig()->render('@KachnitelAdmin/components/EntityList/_BatchActionButton.html.twig', [
            'action' => new BatchAction(name: 'publish', label: 'Publish', icon: '📦', url: '/publish'),
            'selectedIds' => [1, 2],
            'entityClass' => TestEntity::class,
            'entityShortClass' => 'TestEntity',
        ]);

        $this->assertMatchesRegularExpression('/📦\s+Publish \(2\)/u', $html);
    }

    public function testBuiltInActionTemplatesRenderTheirIconViaPartial(): void
    {
        $loader = $this->twig()->getLoader();

        foreach (['ArchiveButton', 'DeleteButton', 'InlineEditButton'] as $name) {
            $source = $loader->getSourceContext('@KachnitelAdmin/components/AdminAction/' . $name . '.html.twig')->getCode();
            $this->assertStringContainsString('components/_Icon.html.twig', $source, $name . ' must render its icon via the partial');
        }
    }
}
