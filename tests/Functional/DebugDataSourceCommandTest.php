<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Command\DebugDataSourceCommand;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class DebugDataSourceCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $command = $application->find('debug:datasource');

        $this->commandTester = new CommandTester($command);
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testListDataSourcesShowsRegisteredDataSourcesAndPromptsForSelection(): void
    {
        $this->commandTester->setInputs(['TestEntity']);
        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        // Should show the TestEntity data source from fixtures
        $this->assertStringContainsString('TestEntity', $output);
        $this->assertStringContainsString('Registered Data Sources', $output);
        $this->assertStringContainsString('Select a data source to see details', $output);
        // After selection, should show details
        $this->assertStringContainsString('Data Source:', $output);
    }

    public function testShowDetailsForTestEntity(): void
    {
        $this->commandTester->execute(['--identifier' => 'TestEntity']);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        // Check basic information is displayed
        $this->assertStringContainsString('Data Source:', $output);
        $this->assertStringContainsString('TestEntity', $output);

        // Check sections are present
        $this->assertStringContainsString('Basic Information', $output);
        $this->assertStringContainsString('Pagination Defaults', $output);
        $this->assertStringContainsString('Supported Actions', $output);
        $this->assertStringContainsString('Columns', $output);
        $this->assertStringContainsString('Filters', $output);
    }

    public function testShowDetailsWithShortOption(): void
    {
        $this->commandTester->execute(['-i' => 'TestEntity']);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Data Source:', $output);
        $this->assertStringContainsString('TestEntity', $output);
    }

    public function testShowErrorForNonExistentDataSource(): void
    {
        $this->commandTester->execute(['--identifier' => 'NonExistent']);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Data source "NonExistent" not found', $output);
        $this->assertStringContainsString('Available identifiers:', $output);
    }

    public function testCommandIsRegistered(): void
    {
        $container = static::getContainer();
        $command = $container->get(DebugDataSourceCommand::class);

        $this->assertInstanceOf(DebugDataSourceCommand::class, $command);
    }
}
