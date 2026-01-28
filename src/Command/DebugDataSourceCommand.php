<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Command;

use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\DataSource\FilterMetadata;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @SuppressWarnings(PHPMD.CyclomaticComplexity) Debug output requires conditional formatting
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength) Methods delegate to helpers, PHPMD counts incorrectly
 */
#[AsCommand(
    name: 'debug:datasource',
    description: 'Debug registered data sources and their configuration'
)]
class DebugDataSourceCommand extends Command
{
    public function __construct(
        private readonly DataSourceRegistry $registry
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'identifier',
            'i',
            InputOption::VALUE_REQUIRED,
            'Show details for a specific data source by identifier'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $identifier = $input->getOption('identifier');

        if ($identifier !== null) {
            return $this->showDataSourceDetails($io, $identifier);
        }

        return $this->listDataSources($io);
    }

    private function listDataSources(SymfonyStyle $io): int
    {
        $dataSources = $this->registry->all();

        if (count($dataSources) === 0) {
            $io->warning('No data sources registered.');

            return Command::SUCCESS;
        }

        $io->title('Registered Data Sources');
        $io->text(sprintf('Found %d data source(s)', count($dataSources)));
        $io->newLine();

        $rows = [];
        foreach ($dataSources as $dataSource) {
            $rows[] = [
                $dataSource->getIdentifier(),
                $dataSource->getLabel(),
                $this->getSourceType($dataSource),
                $dataSource::class,
            ];
        }

        $io->table(['Identifier', 'Label', 'Type', 'Class'], $rows);

        $identifiers = $this->registry->getIdentifiers();
        $selectedIdentifier = $io->choice('Select a data source to see details:', $identifiers);

        return $this->showDataSourceDetails($io, $selectedIdentifier);
    }

    private function showDataSourceDetails(SymfonyStyle $io, string $identifier): int
    {
        $dataSource = $this->registry->get($identifier);

        if ($dataSource === null) {
            $io->error(sprintf('Data source "%s" not found.', $identifier));
            $io->note('Available identifiers: ' . implode(', ', $this->registry->getIdentifiers()));

            return Command::FAILURE;
        }

        $io->title(sprintf('Data Source: %s', $dataSource->getLabel()));

        $this->displayBasicInfo($io, $dataSource);
        $this->displaySupportedActions($io, $dataSource);
        $this->displayColumns($io, $dataSource);
        $this->displayFilters($io, $dataSource);

        return Command::SUCCESS;
    }

    private function displayBasicInfo(SymfonyStyle $io, DataSourceInterface $dataSource): void
    {
        $io->section('Basic Information');
        $io->definitionList(
            ['Identifier' => $dataSource->getIdentifier()],
            ['Label' => $dataSource->getLabel()],
            ['Icon' => $dataSource->getIcon() ?? '<none>'],
            ['Type' => $this->getSourceType($dataSource)],
            ['Class' => $dataSource::class],
            ['ID Field' => $dataSource->getIdField()],
        );

        $io->section('Pagination Defaults');
        $io->definitionList(
            ['Sort By' => $dataSource->getDefaultSortBy()],
            ['Sort Direction' => $dataSource->getDefaultSortDirection()],
            ['Items Per Page' => (string) $dataSource->getDefaultItemsPerPage()],
        );
    }

    private function displaySupportedActions(SymfonyStyle $io, DataSourceInterface $dataSource): void
    {
        $io->section('Supported Actions');
        $actions = ['index', 'show', 'new', 'edit', 'delete', 'batch_delete'];
        $supportedActions = [];

        foreach ($actions as $action) {
            $supportedActions[] = sprintf('%s: %s', $action, $dataSource->supportsAction($action) ? '✓' : '✗');
        }

        $io->listing($supportedActions);
    }

    private function displayColumns(SymfonyStyle $io, DataSourceInterface $dataSource): void
    {
        $io->section('Columns');
        $columns = $dataSource->getColumns();

        if (count($columns) === 0) {
            $io->text('<info>No columns defined</info>');

            return;
        }

        $rows = [];
        foreach ($columns as $name => $metadata) {
            $rows[] = [$name, $metadata->label, $metadata->type, $metadata->sortable ? '✓' : '✗', $metadata->template ?? '-'];
        }

        $io->table(['Name', 'Label', 'Type', 'Sortable', 'Template'], $rows);
    }

    private function displayFilters(SymfonyStyle $io, DataSourceInterface $dataSource): void
    {
        $io->section('Filters');
        $filters = $dataSource->getFilters();

        if (count($filters) === 0) {
            $io->text('<info>No filters defined</info>');

            return;
        }

        $rows = [];
        foreach ($filters as $name => $metadata) {
            $rows[] = [$name, $metadata->label ?? $name, $metadata->type, $metadata->operator, $this->getEnumInfo($metadata)];
        }

        $io->table(['Name', 'Label', 'Type', 'Operator', 'Enum Options'], $rows);
    }

    private function getEnumInfo(FilterMetadata $metadata): string
    {
        if ($metadata->enumOptions === null) {
            return '-';
        }

        if ($metadata->enumOptions->values !== null) {
            return (string) count($metadata->enumOptions->values);
        }

        if ($metadata->enumOptions->enumClass !== null) {
            return $metadata->enumOptions->enumClass;
        }

        return '-';
    }

    private function getSourceType(DataSourceInterface $dataSource): string
    {
        return $dataSource instanceof DoctrineDataSource ? 'Doctrine' : 'Custom';
    }
}
