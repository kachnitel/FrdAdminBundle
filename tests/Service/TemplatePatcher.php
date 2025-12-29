<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Service;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 * Exception thrown when a template patch cannot be applied.
 */
final class TemplatePatchException extends \RuntimeException
{
}

/**
 * Applies context-based patches to templates.
 *
 * Supports line-agnostic patching by using context strings
 * instead of line numbers for insertion points.
 */
final class TemplatePatcher
{
    /**
     * Apply a patch configuration to source content.
     *
     * @param array{
     *     marker: string,
     *     insertPoint: 'prepend'|array{context: string, position: 'before'|'after'}
     * } $patchConfig
     *
     * @throws TemplatePatchException When context is not found or ambiguous
     */
    public function apply(string $sourceContent, array $patchConfig): string
    {
        $insertPoint = $patchConfig['insertPoint'];
        $marker = $patchConfig['marker'];

        if ($insertPoint === 'prepend') {
            return $marker . $sourceContent;
        }

        // Context-based insertion
        /** @var array{context: string, position: 'before'|'after'} $insertPoint */
        $context = $insertPoint['context'];
        $position = $insertPoint['position'];

        $contextPos = strpos($sourceContent, $context);
        if ($contextPos === false) {
            throw new TemplatePatchException(sprintf(
                "Context not found: '%s'. The source template may have changed.",
                $this->truncateContext($context)
            ));
        }

        // Check for ambiguity
        if (substr_count($sourceContent, $context) > 1) {
            throw new TemplatePatchException(sprintf(
                "Ambiguous context: '%s' found multiple times. Use a more unique context string.",
                $this->truncateContext($context)
            ));
        }

        if ($position === 'before') {
            return substr($sourceContent, 0, $contextPos)
                . $marker
                . substr($sourceContent, $contextPos);
        }

        // 'after' - insert after the context string
        $insertPos = $contextPos + strlen($context);

        return substr($sourceContent, 0, $insertPos)
            . $marker
            . substr($sourceContent, $insertPos);
    }

    /**
     * Generate a unified diff between two strings.
     */
    public function generateDiff(string $from, string $to): string
    {
        $differ = new Differ(new UnifiedDiffOutputBuilder("--- Current\n+++ Expected\n"));

        return $differ->diff($from, $to);
    }

    /**
     * Truncate context string for display in error messages.
     */
    private function truncateContext(string $context): string
    {
        $maxLength = 50;
        if (strlen($context) <= $maxLength) {
            return $context;
        }

        return substr($context, 0, $maxLength) . '...';
    }
}
