<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Command\DebugFiltersCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class DebugFiltersCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);
        $command = $application->find('admin:debug:filters');

        $this->commandTester = new CommandTester($command);
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @test
     */
    public function commandIsRegistered(): void
    {
        $container = static::getContainer();
        $command = $container->get(DebugFiltersCommand::class);

        $this->assertInstanceOf(DebugFiltersCommand::class, $command);
    }

    /**
     * @test
     */
    public function listEntitiesShowsAdminEntitiesAndPromptsForSelection(): void
    {
        $this->commandTester->setInputs(['TestEntity']);
        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        // Should show the admin entities table
        $this->assertStringContainsString('Admin Entities', $output);
        $this->assertStringContainsString('TestEntity', $output);
        $this->assertStringContainsString('Select an entity to inspect:', $output);
    }

    /**
     * @test
     */
    public function showFiltersForTestEntity(): void
    {
        $this->commandTester->execute(['entityClass' => 'TestEntity']);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        // Check filter metadata is displayed
        $this->assertStringContainsString('Filter Metadata for', $output);
        $this->assertStringContainsString('name', $output);
        $this->assertStringContainsString('text', $output);
        $this->assertStringContainsString('quantity', $output);
        $this->assertStringContainsString('number', $output);
    }

    /**
     * @test
     */
    public function showFiltersDisplaysEnumType(): void
    {
        $this->commandTester->execute(['entityClass' => 'TestEntity']);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        // Status should be detected as enum
        $this->assertStringContainsString('status', $output);
        $this->assertStringContainsString('enum', $output);
        $this->assertStringContainsString('TestStatus', $output);
    }

    /**
     * @test
     */
    public function showFiltersDisplaysRelationType(): void
    {
        $this->commandTester->execute(['entityClass' => 'TestEntity']);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        // relatedEntity should be relation type with search fields
        $this->assertStringContainsString('relatedEntity', $output);
        $this->assertStringContainsString('relation', $output);
        $this->assertStringContainsString('Search Fields', $output);
    }

    /**
     * @test
     */
    public function showFiltersWithVerboseOutput(): void
    {
        $this->commandTester->execute(
            ['entityClass' => 'TestEntity'],
            ['verbosity' => \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE]
        );

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        // Verbose mode should show type detection explanation
        $this->assertStringContainsString('Type detection:', $output);
    }

    /**
     * @test
     */
    public function showErrorForNonExistentEntity(): void
    {
        $this->commandTester->execute(['entityClass' => 'NonExistentEntity']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('not found', $output);
    }

    /**
     * @test
     */
    public function showAllOptionListsNonAdminEntities(): void
    {
        $this->commandTester->setInputs(['RelatedEntity']);
        $this->commandTester->execute(['--all' => true]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        // RelatedEntity doesn't have #[Admin] but should show with --all flag
        $this->assertStringContainsString('RelatedEntity', $output);
    }

    /**
     * @test
     */
    public function showSkippedPropertiesInVerboseMode(): void
    {
        $this->commandTester->execute(
            ['entityClass' => 'TestEntity'],
            ['verbosity' => \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE]
        );

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        // Should show skipped properties section
        $this->assertStringContainsString('Skipped Properties', $output);
        // disabledFilter should be in skipped list because it has enabled: false
        $this->assertStringContainsString('disabledFilter', $output);
    }

    /**
     * @test
     */
    public function showMultiplePropertyForEnumFilters(): void
    {
        $this->commandTester->execute(['entityClass' => 'TestEntity']);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        // Should show Multiple field for enum filters
        $this->assertStringContainsString('Multiple', $output);
    }
}
