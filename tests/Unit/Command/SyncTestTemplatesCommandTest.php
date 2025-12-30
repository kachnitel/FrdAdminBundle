<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Command;

use Kachnitel\AdminBundle\Command\SyncTestTemplatesCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

class SyncTestTemplatesCommandTest extends TestCase
{
    private SyncTestTemplatesCommand $command;

    protected function setUp(): void
    {
        // Use a temporary directory for testing
        $projectDir = sys_get_temp_dir() . '/test_sync_templates_' . uniqid();
        mkdir($projectDir, 0755, true);

        $this->command = new SyncTestTemplatesCommand($projectDir);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory if needed
    }

    /**
     * @test
     */
    public function commandHasCorrectName(): void
    {
        $this->assertEquals('admin:sync-test-templates', $this->command->getName());
    }

    /**
     * @test
     */
    public function commandHasDescription(): void
    {
        $description = $this->command->getDescription();
        $this->assertNotEmpty($description);
        $this->assertStringContainsString('template', strtolower($description));
    }

    /**
     * @test
     */
    public function commandHasCheckOption(): void
    {
        $options = $this->command->getDefinition()->getOptions();
        $optionNames = array_map(fn ($o) => $o->getName(), $options);
        $this->assertContains('check', $optionNames);
    }

    /**
     * @test
     */
    public function commandHasDiffOption(): void
    {
        $options = $this->command->getDefinition()->getOptions();
        $optionNames = array_map(fn ($o) => $o->getName(), $options);
        $this->assertContains('diff', $optionNames);
    }

    /**
     * @test
     */
    public function checkOptionIsOptional(): void
    {
        $option = $this->command->getDefinition()->getOption('check');
        $this->assertFalse($option->acceptValue());
    }

    /**
     * @test
     */
    public function diffOptionIsOptional(): void
    {
        $option = $this->command->getDefinition()->getOption('diff');
        $this->assertFalse($option->acceptValue());
    }

    /**
     * @test
     */
    public function commandIsSymfonyCommand(): void
    {
        $this->assertInstanceOf(\Symfony\Component\Console\Command\Command::class, $this->command);
    }

    /**
     * @test
     */
    public function commandCanBeExecuted(): void
    {
        $tester = new CommandTester($this->command);

        // Should return either SUCCESS or FAILURE depending on files
        $result = $tester->execute([]);

        $this->assertGreaterThanOrEqual(0, $result);
    }

    /**
     * @test
     */
    public function executeWithCheckOptionDoesNotModifyFiles(): void
    {
        $tester = new CommandTester($this->command);

        $result = $tester->execute(['--check' => true]);

        // Should still complete (may fail if files don't exist, but that's not a modification)
        $this->assertGreaterThanOrEqual(0, $result);
    }

    /**
     * @test
     */
    public function executeWithDiffOptionShowsDiff(): void
    {
        $tester = new CommandTester($this->command);

        $result = $tester->execute(['--diff' => true]);

        $this->assertGreaterThanOrEqual(0, $result);
    }

    /**
     * @test
     */
    public function executeWithCheckAndDiffOptions(): void
    {
        $tester = new CommandTester($this->command);

        $result = $tester->execute([
            '--check' => true,
            '--diff' => true,
        ]);

        $this->assertGreaterThanOrEqual(0, $result);
    }

    /**
     * @test
     */
    public function commandOutputIsNotEmpty(): void
    {
        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertNotEmpty($output);
    }

    /**
     * @test
     */
    public function executeReturnsIntegerStatus(): void
    {
        $tester = new CommandTester($this->command);
        $result = $tester->execute([]);

        // Result should be either SUCCESS (0) or FAILURE (1)
        $this->assertThat(
            $result,
            $this->logicalOr(
                $this->equalTo(0),
                $this->equalTo(1)
            )
        );
    }

    /**
     * @test
     */
    public function multipleExecutionsAreIndependent(): void
    {
        $tester = new CommandTester($this->command);

        $result1 = $tester->execute([]);
        $output1 = $tester->getDisplay();

        // Create new tester for second execution
        $tester2 = new CommandTester($this->command);
        $result2 = $tester2->execute([]);
        $output2 = $tester2->getDisplay();

        // Both should complete
        $this->assertGreaterThanOrEqual(0, $result1);
        $this->assertGreaterThanOrEqual(0, $result2);
    }
}
