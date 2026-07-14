<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * Base test case for component tests with common database setup/teardown.
 */
abstract class ComponentTestCase extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected EntityManagerInterface $em;

    protected static function getKernelClass(): string
    {
        return ComponentTestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->em = $container->get('doctrine')->getManager();

        // Create database schema for all test entities
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->em);

        // Drop existing schema to ensure clean state
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        // Drop database schema after each test
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->dropSchema($metadata);

        parent::tearDown();

        // restore_exception_handler();
    }

    // ── Action rendering helpers ───────────────────────────────────────────────

    /**
     * Assert that an action icon appears at least once in the rendered output.
     *
     * Using the icon rather than the label avoids false positives from entity
     * names or other text that happen to contain the label word (e.g. an entity
     * named "Archive Things" would match a label-based assertion for "Archive").
     *
     * @param string $icon     The emoji icon declared on the action (e.g. '✅', '🗃', '📦')
     * @param string $rendered The full rendered HTML string from $component->render()
     * @param string $message  Optional failure message
     */
    protected function assertActionRendered(string $icon, string $rendered, string $message = ''): void
    {
        $message = $message ?: sprintf('Expected action icon "%s" to be present in rendered output.', $icon);
        $this->assertStringContainsString($icon, $rendered, $message);
    }

    /**
     * Assert that an action icon does NOT appear in the rendered output.
     *
     * @param string $icon     The emoji icon declared on the action (e.g. '✅', '🗃', '📦')
     * @param string $rendered The full rendered HTML string from $component->render()
     * @param string $message  Optional failure message
     */
    protected function assertActionNotRendered(string $icon, string $rendered, string $message = ''): void
    {
        $message = $message ?: sprintf('Expected action icon "%s" to be absent from rendered output.', $icon);
        $this->assertStringNotContainsString($icon, $rendered, $message);
    }

    /**
     * Assert that an action icon appears exactly $count times in the rendered output.
     *
     * Use this when multiple rows are present and only a subset should show the action,
     * e.g. assertActionRenderedCount('✅', 1, $rendered) verifies per-row condition evaluation.
     *
     * @param string $icon     The emoji icon declared on the action (e.g. '✅', '🗃', '📦')
     * @param int    $count    Expected number of occurrences
     * @param string $rendered The full rendered HTML string from $component->render()
     * @param string $message  Optional failure message
     */
    protected function assertActionRenderedCount(string $icon, int $count, string $rendered, string $message = ''): void
    {
        $message = $message ?: sprintf(
            'Expected action icon "%s" to appear exactly %d time(s) in rendered output.',
            $icon,
            $count,
        );
        $this->assertSame($count, substr_count($rendered, $icon), $message);
    }
}
