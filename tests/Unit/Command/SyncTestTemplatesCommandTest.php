<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Command;

use Kachnitel\AdminBundle\Command\SyncTestTemplatesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group sync-test-templates
 */
class SyncTestTemplatesCommandTest extends TestCase
{
    private string $projectDir;
    private SyncTestTemplatesCommand $command;

    /** Source paths relative to $projectDir, mirroring TEMPLATE_MAPPINGS keys */
    private const SOURCE_ENTITY_LIST = 'templates/components/EntityList.html.twig';
    private const SOURCE_PREVIEW     = 'templates/types/_preview.html.twig';

    /** Target paths relative to $projectDir, mirroring TEMPLATE_MAPPINGS targets */
    private const TARGET_ENTITY_LIST = 'tests/templates/bundles/KachnitelAdminBundle/components/EntityList.html.twig';
    private const TARGET_PREVIEW     = 'tests/templates/bundles/KachnitelAdminBundle/types/_preview.html.twig';

    private const MARKER_ENTITY_LIST = "{# Override marker - DO NOT REMOVE #}\n<!-- TEST_OVERRIDE:ENTITY_LIST -->\n";
    private const MARKER_PREVIEW     = "{# Override marker - DO NOT REMOVE #}\n<!-- TEST_OVERRIDE:PREVIEW -->\n";

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/test_sync_templates_' . uniqid();
        mkdir($this->projectDir, 0755, true);
        $this->command = new SyncTestTemplatesCommand($this->projectDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    // -------------------------------------------------------------------------
    // Structural / metadata
    // -------------------------------------------------------------------------

    /** @test */
    public function commandHasCorrectName(): void
    {
        $this->assertSame('admin:sync-test-templates', $this->command->getName());
    }

    /** @test */
    public function commandHasCheckOption(): void
    {
        $optionNames = array_keys($this->command->getDefinition()->getOptions());
        $this->assertContains('check', $optionNames);
        $this->assertFalse($this->command->getDefinition()->getOption('check')->acceptValue());
    }

    /** @test */
    public function commandHasDiffOption(): void
    {
        $optionNames = array_keys($this->command->getDefinition()->getOptions());
        $this->assertContains('diff', $optionNames);
        $this->assertFalse($this->command->getDefinition()->getOption('diff')->acceptValue());
    }

    // -------------------------------------------------------------------------
    // Core patching behaviour
    // -------------------------------------------------------------------------

    /** @test */
    public function syncsTemplatesByPrependingMarkersAndReturnsSuccess(): void
    {
        $this->seedSources();

        $tester = new CommandTester($this->command);
        $result = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $result);

        $entityListTarget = $this->projectDir . '/' . self::TARGET_ENTITY_LIST;
        $previewTarget    = $this->projectDir . '/' . self::TARGET_PREVIEW;

        $this->assertFileExists($entityListTarget);
        $this->assertFileExists($previewTarget);

        $this->assertStringStartsWith(
            self::MARKER_ENTITY_LIST,
            (string) file_get_contents($entityListTarget),
            'EntityList target must start with its marker'
        );
        $this->assertStringStartsWith(
            self::MARKER_PREVIEW,
            (string) file_get_contents($previewTarget),
            'Preview target must start with its marker'
        );
    }

    /** @test */
    public function targetContainsFullSourceContentAfterMarker(): void
    {
        $sourceContent = $this->entityListSourceContent();
        $this->seedSources();

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $targetContent = (string) file_get_contents($this->projectDir . '/' . self::TARGET_ENTITY_LIST);
        $expectedContent = self::MARKER_ENTITY_LIST . $sourceContent;

        $this->assertSame($expectedContent, $targetContent);
    }

    /** @test */
    public function outputReportsSyncedTemplateCount(): void
    {
        $this->seedSources();

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertStringContainsString('Synced 2 template(s)', $tester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // Idempotency
    // -------------------------------------------------------------------------

    /** @test */
    public function secondRunIsIdempotentWhenAlreadyInSync(): void
    {
        $this->seedSources();
        $tester = new CommandTester($this->command);

        // First run writes the targets
        $tester->execute([]);

        $targetPath = $this->projectDir . '/' . self::TARGET_ENTITY_LIST;
        $contentAfterFirstRun = (string) file_get_contents($targetPath);
        $mtimeAfterFirstRun   = filemtime($targetPath);

        // Second run — source unchanged, target already correct
        $result = (new CommandTester($this->command))->execute([]);

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertSame($contentAfterFirstRun, (string) file_get_contents($targetPath), 'File must not be rewritten');

        // Filesystem mtime should not advance because dumpFile must not be called
        $this->assertSame($mtimeAfterFirstRun, filemtime($targetPath), 'File mtime must not change on a no-op run');
    }

    // -------------------------------------------------------------------------
    // Missing source
    // -------------------------------------------------------------------------

    /** @test */
    public function missingSourceTemplateProducesWarningButNotFailure(): void
    {
        // Seed only one of the two sources
        $this->seedSource(self::SOURCE_PREVIEW, $this->previewSourceContent());

        $tester = new CommandTester($this->command);
        $result = $tester->execute([]);

        // The command warns but still succeeds for the templates it could process
        $this->assertSame(Command::SUCCESS, $result);
        $this->assertStringContainsString('not found', strtolower($tester->getDisplay()));

        // The seeded template must still have been synced
        $this->assertFileExists($this->projectDir . '/' . self::TARGET_PREVIEW);
    }

    // -------------------------------------------------------------------------
    // --check option
    // -------------------------------------------------------------------------

    /** @test */
    public function checkOptionReturnsFailureWhenTargetsAreOutOfSync(): void
    {
        $this->seedSources();

        $tester = new CommandTester($this->command);
        $result = $tester->execute(['--check' => true]);

        // Targets don't exist yet → out of sync → FAILURE
        $this->assertSame(Command::FAILURE, $result);
    }

    /** @test */
    public function checkOptionDoesNotWriteTargetFiles(): void
    {
        $this->seedSources();

        $tester = new CommandTester($this->command);
        $tester->execute(['--check' => true]);

        $this->assertFileDoesNotExist(
            $this->projectDir . '/' . self::TARGET_ENTITY_LIST,
            '--check must never write target files'
        );
        $this->assertFileDoesNotExist(
            $this->projectDir . '/' . self::TARGET_PREVIEW,
            '--check must never write target files'
        );
    }

    /** @test */
    public function checkOptionReturnsSuccessWhenTargetsAreAlreadyInSync(): void
    {
        $this->seedSources();

        // First pass: write the targets
        (new CommandTester($this->command))->execute([]);

        // Second pass: check only
        $tester = new CommandTester($this->command);
        $result = $tester->execute(['--check' => true]);

        $this->assertSame(Command::SUCCESS, $result);
    }

    /** @test */
    public function checkOptionOutputsOutOfSyncMessage(): void
    {
        $this->seedSources();

        $tester = new CommandTester($this->command);
        $tester->execute(['--check' => true]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('out of sync', strtolower($output));
    }

    // -------------------------------------------------------------------------
    // --diff option
    // -------------------------------------------------------------------------

    /** @test */
    public function diffOptionShowsDiffWhenTargetExistsButIsOutOfSync(): void
    {
        $this->seedSources();

        // Write a stale target (missing the marker)
        $staleContent = $this->entityListSourceContent();
        $this->writeFile(self::TARGET_ENTITY_LIST, $staleContent);

        $tester = new CommandTester($this->command);
        $tester->execute(['--diff' => true]);

        $output = $tester->getDisplay();

        // // A unified diff includes +/- lines
        // $this->assertMatchesRegularExpression('/^\+/m', $output, 'Diff output must contain added lines');
        // $this->assertMatchesRegularExpression('/^-/m', $output, 'Diff output must contain removed lines');
        // Prepending a marker is purely additive — the diff shows only added lines
        $this->assertMatchesRegularExpression('/^\+/m', $output, 'Diff output must contain added lines');
        $this->assertStringContainsString('TEST_OVERRIDE:ENTITY_LIST', $output, 'Diff must show the inserted marker');
    }

    /** @test */
    public function diffOptionCombinedWithCheckStillRefusesToWrite(): void
    {
        $this->seedSources();

        $tester = new CommandTester($this->command);
        $result = $tester->execute(['--check' => true, '--diff' => true]);

        $this->assertSame(Command::FAILURE, $result);
        $this->assertFileDoesNotExist($this->projectDir . '/' . self::TARGET_ENTITY_LIST);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function seedSources(): void
    {
        $this->seedSource(self::SOURCE_ENTITY_LIST, $this->entityListSourceContent());
        $this->seedSource(self::SOURCE_PREVIEW, $this->previewSourceContent());
    }

    private function seedSource(string $relPath, string $content): void
    {
        $this->writeFile($relPath, $content);
    }

    private function writeFile(string $relPath, string $content): void
    {
        $fullPath = $this->projectDir . '/' . $relPath;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($fullPath, $content);
    }

    private function entityListSourceContent(): string
    {
        return <<<'TWIG'
<div {{ attributes }}>
    {% for item in items %}
        <tr>{{ item.name }}</tr>
    {% endfor %}
</div>
TWIG;
    }

    private function previewSourceContent(): string
    {
        return <<<'TWIG'
<span class="preview">{{ value }}</span>
TWIG;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
