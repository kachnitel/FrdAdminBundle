<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Kachnitel\AdminBundle\Tests\Service\TemplatePatcher;
use Kachnitel\AdminBundle\Tests\Service\TemplatePatchException;
use PHPUnit\Framework\TestCase;

class TemplatePatcherTest extends TestCase
{
    private TemplatePatcher $patcher;

    protected function setUp(): void
    {
        $this->patcher = new TemplatePatcher();
    }

    public function testPrependMarker(): void
    {
        $source = "{# Template #}\n<div>Content</div>";
        $config = [
            'marker' => "<!-- MARKER -->\n",
            'insertPoint' => 'prepend',
        ];

        $result = $this->patcher->apply($source, $config);

        $this->assertStringStartsWith("<!-- MARKER -->\n", $result);
        $this->assertStringContainsString("{# Template #}", $result);
    }

    public function testInsertBeforeContext(): void
    {
        $source = "{# Template #}\n<div>Content</div>";
        $config = [
            'marker' => "<!-- MARKER -->\n",
            'insertPoint' => ['context' => '<div>', 'position' => 'before'],
        ];

        $result = $this->patcher->apply($source, $config);

        $this->assertSame("{# Template #}\n<!-- MARKER -->\n<div>Content</div>", $result);
    }

    public function testInsertAfterContext(): void
    {
        $source = "{# Template #}\n<div>Content</div>";
        $config = [
            'marker' => "<!-- MARKER -->",
            'insertPoint' => ['context' => "{# Template #}\n", 'position' => 'after'],
        ];

        $result = $this->patcher->apply($source, $config);

        $this->assertSame("{# Template #}\n<!-- MARKER --><div>Content</div>", $result);
    }

    public function testThrowsExceptionWhenContextNotFound(): void
    {
        $source = "{# Template #}\n<div>Content</div>";
        $config = [
            'marker' => "<!-- MARKER -->\n",
            'insertPoint' => ['context' => 'NOT_FOUND', 'position' => 'before'],
        ];

        $this->expectException(TemplatePatchException::class);
        $this->expectExceptionMessage("Context not found: 'NOT_FOUND'");

        $this->patcher->apply($source, $config);
    }

    public function testThrowsExceptionWhenContextIsAmbiguous(): void
    {
        $source = "<div>One</div>\n<div>Two</div>";
        $config = [
            'marker' => "<!-- MARKER -->\n",
            'insertPoint' => ['context' => '<div>', 'position' => 'before'],
        ];

        $this->expectException(TemplatePatchException::class);
        $this->expectExceptionMessage("Ambiguous context: '<div>' found multiple times");

        $this->patcher->apply($source, $config);
    }

    public function testTruncatesLongContextInErrorMessage(): void
    {
        $source = "{# Template #}\n<div>Content</div>";
        $longContext = str_repeat('x', 100);
        $config = [
            'marker' => "<!-- MARKER -->\n",
            'insertPoint' => ['context' => $longContext, 'position' => 'before'],
        ];

        $this->expectException(TemplatePatchException::class);
        $this->expectExceptionMessage(str_repeat('x', 50) . '...');

        $this->patcher->apply($source, $config);
    }

    public function testGenerateDiff(): void
    {
        $from = "line1\nline2\nline3";
        $to = "line1\nmodified\nline3";

        $diff = $this->patcher->generateDiff($from, $to);

        $this->assertStringContainsString('-line2', $diff);
        $this->assertStringContainsString('+modified', $diff);
    }

    public function testPreservesMidFileContext(): void
    {
        $source = "{% block content %}\n    <h1>Title</h1>\n    <p>Body</p>\n{% endblock %}";
        $config = [
            'marker' => "{# INJECTED #}\n",
            'insertPoint' => ['context' => '<h1>Title</h1>', 'position' => 'after'],
        ];

        $result = $this->patcher->apply($source, $config);

        $expected = "{% block content %}\n    <h1>Title</h1>{# INJECTED #}\n\n    <p>Body</p>\n{% endblock %}";
        $this->assertSame($expected, $result);
    }
}
