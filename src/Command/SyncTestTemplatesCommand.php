<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Command;

use Kachnitel\AdminBundle\Tests\Service\TemplatePatcher;
use Kachnitel\AdminBundle\Tests\Service\TemplatePatchException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Syncs test template overrides with main bundle templates.
 *
 * Test templates need a marker comment to verify overrides work,
 * but otherwise should match the main templates.
 *
 * This command supports context-based patching for line-agnostic
 * insertion of markers into templates.
 */
#[AsCommand(
    name: 'admin:sync-test-templates',
    description: 'Sync test template overrides with main bundle templates',
)]
class SyncTestTemplatesCommand extends Command
{
    /**
     * Template mappings with patch configuration.
     *
     * Each entry maps a source template to its test override configuration:
     * - target: Path to the test template override
     * - marker: The marker content to insert
     * - insertPoint: Where to insert the marker:
     *   - 'prepend': Insert at the beginning of the file
     *   - ['context' => string, 'position' => 'before'|'after']: Insert relative to context
     *
     * @var array<string, array{
     *     target: string,
     *     marker: string,
     *     insertPoint: 'prepend'|array{context: string, position: 'before'|'after'}
     * }>
     */
    private const TEMPLATE_MAPPINGS = [
        'templates/components/EntityList.html.twig' => [
            'target' => 'tests/templates/bundles/KachnitelAdminBundle/components/EntityList.html.twig',
            'marker' => "{# Override marker - DO NOT REMOVE #}\n<!-- TEST_OVERRIDE:ENTITY_LIST -->\n",
            'insertPoint' => 'prepend',
        ],
        'templates/types/_preview.html.twig' => [
            'target' => 'tests/templates/bundles/KachnitelAdminBundle/types/_preview.html.twig',
            'marker' => "{# Override marker - DO NOT REMOVE #}\n<!-- TEST_OVERRIDE:PREVIEW -->\n",
            'insertPoint' => 'prepend',
        ],
    ];

    public function __construct(
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'check',
                null,
                InputOption::VALUE_NONE,
                'Check if templates are in sync without modifying'
            )
            ->addOption(
                'diff',
                null,
                InputOption::VALUE_NONE,
                'Show diff of changes'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fs = new Filesystem();
        $patcher = new TemplatePatcher();
        $checkOnly = (bool) $input->getOption('check');
        $showDiff = (bool) $input->getOption('diff');

        $synced = 0;
        $errors = [];

        foreach (self::TEMPLATE_MAPPINGS as $source => $config) {
            $sourcePath = $this->projectDir . '/' . $source;
            $targetPath = $this->projectDir . '/' . $config['target'];

            if (!$fs->exists($sourcePath)) {
                $io->warning(sprintf('Source template not found: %s', $source));
                continue;
            }

            $sourceContent = file_get_contents($sourcePath);
            if ($sourceContent === false) {
                $errors[] = sprintf('Failed to read: %s', $source);
                continue;
            }

            try {
                $targetContent = $patcher->apply($sourceContent, $config);
            } catch (TemplatePatchException $e) {
                $errors[] = sprintf(
                    "Failed to patch %s: %s\n\n" .
                    "This usually means the source template changed in a way that breaks the patch.\n" .
                    "Please update the patch configuration in SyncTestTemplatesCommand::TEMPLATE_MAPPINGS.",
                    $source,
                    $e->getMessage()
                );
                continue;
            }

            // Check if already in sync
            $currentTarget = $fs->exists($targetPath) ? (string) file_get_contents($targetPath) : '';
            if ($currentTarget === $targetContent) {
                if ($io->isVerbose()) {
                    $io->text(sprintf('<info>✓</info> Already in sync: %s', $source));
                }
                $synced++;
                continue;
            }

            if ($showDiff && $currentTarget !== '') {
                $diff = $patcher->generateDiff($currentTarget, $targetContent);
                $io->text($diff);
            }

            if ($checkOnly) {
                $errors[] = sprintf(
                    "Template out of sync: %s -> %s\nRun without --check to update.",
                    $source,
                    $config['target']
                );
                continue;
            }

            // Write the synced template
            $fs->mkdir(dirname($targetPath));
            $fs->dumpFile($targetPath, $targetContent);

            $io->text(sprintf('Synced: %s -> %s', $source, $config['target']));
            $synced++;
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $io->error($error);
            }

            return Command::FAILURE;
        }

        if (!$io->isQuiet()) {
            $io->success(sprintf('Synced %d template(s)', $synced));
        }

        return Command::SUCCESS;
    }
}
