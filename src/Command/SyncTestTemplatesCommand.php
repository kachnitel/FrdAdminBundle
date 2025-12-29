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
 *
 * TODO: Many templates override just to remove routes unavailable in tests, we could take care of these or mock route check in tests
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
        $filesystem = new Filesystem();
        $patcher = new TemplatePatcher();
        $checkOnly = (bool) $input->getOption('check');
        $showDiff = (bool) $input->getOption('diff');

        $synced = 0;
        $errors = [];

        foreach (self::TEMPLATE_MAPPINGS as $source => $config) {
            $templateErrors = $this->processTemplate(
                $source,
                $config,
                $filesystem,
                $patcher,
                $io,
                $checkOnly,
                $showDiff
            );

            if (!empty($templateErrors)) {
                $errors = array_merge($errors, $templateErrors);
            } else {
                $synced++;
            }
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

    /**
     * Process a single template mapping.
     *
     * @param string $source Source template path (relative to project root)
     * @param array<string, mixed> $config Template patch configuration
     * @param Filesystem $filesystem Filesystem operations handler
     * @param TemplatePatcher $patcher Template patcher service
     * @param SymfonyStyle $io Console output handler
     * @param bool $checkOnly If true, only check without modifying files
     * @param bool $showDiff If true, display diff of changes
     *
     * @return array<int, string> Array of error messages (empty if successful)
     */
    private function processTemplate(
        string $source,
        array $config,
        Filesystem $filesystem,
        TemplatePatcher $patcher,
        SymfonyStyle $io,
        bool $checkOnly,
        bool $showDiff
    ): array {
        $sourcePath = $this->projectDir . '/' . $source;
        $targetPath = $this->projectDir . '/' . $config['target'];

        if (!$filesystem->exists($sourcePath)) {
            $io->warning(sprintf('Source template not found: %s', $source));
            return [];
        }

        $sourceContent = $this->readSourceFile($source);
        if ($sourceContent === null) {
            return [sprintf('Failed to read: %s', $source)];
        }

        $targetContent = $this->generateTargetContent($sourceContent, $config, $patcher);
        if ($targetContent === null) {
            return [
                sprintf(
                    "Failed to patch %s\n\n" .
                    "This usually means the source template changed in a way that breaks the patch.\n" .
                    "Please update the patch configuration in SyncTestTemplatesCommand::TEMPLATE_MAPPINGS.",
                    $source
                ),
            ];
        }

        return $this->handleSyncResult(
            $source,
            $config,
            $targetPath,
            $targetContent,
            $filesystem,
            $patcher,
            $io,
            $checkOnly,
            $showDiff
        );
    }

    /**
     * Read the source template file.
     *
     * @return string|null The file contents, or null if read failed
     */
    private function readSourceFile(string $source): ?string
    {
        $sourcePath = $this->projectDir . '/' . $source;
        $content = file_get_contents($sourcePath);
        return $content !== false ? $content : null;
    }

    /**
     * Generate the target template content with patches applied.
     *
     * @param array<string, mixed> $config Template patch configuration
     * @return string|null The patched content, or null if patching failed
     */
    private function generateTargetContent(
        string $sourceContent,
        array $config,
        TemplatePatcher $patcher
    ): ?string {
        try {
            return $patcher->apply($sourceContent, $config);
        } catch (TemplatePatchException) {
            return null;
        }
    }

    /**
     * Handle the result of template syncing.
     *
     * @param array<string, mixed> $config Template patch configuration
     * @return array<int, string> Array of error messages (empty if successful)
     */
    private function handleSyncResult(
        string $source,
        array $config,
        string $targetPath,
        string $targetContent,
        Filesystem $filesystem,
        TemplatePatcher $patcher,
        SymfonyStyle $io,
        bool $checkOnly,
        bool $showDiff
    ): array {
        $currentTarget = $filesystem->exists($targetPath) ? (string) file_get_contents($targetPath) : '';

        if ($currentTarget === $targetContent) {
            if ($io->isVerbose()) {
                $io->text(sprintf('<info>✓</info> Already in sync: %s', $source));
            }
            return [];
        }

        if ($showDiff && $currentTarget !== '') {
            $diff = $patcher->generateDiff($currentTarget, $targetContent);
            $io->text($diff);
        }

        if ($checkOnly) {
            return [
                sprintf(
                    "Template out of sync: %s -> %s\nRun without --check to update.",
                    $source,
                    $config['target']
                ),
            ];
        }

        $filesystem->mkdir(dirname($targetPath));
        $filesystem->dumpFile($targetPath, $targetContent);
        $io->text(sprintf('Synced: %s -> %s', $source, $config['target']));

        return [];
    }
}
