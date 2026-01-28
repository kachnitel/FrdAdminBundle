<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'admin:debug:filters',
    description: 'Debug filter metadata for admin entities'
)]
class DebugFiltersCommand extends Command
{
    private const DISPLAY_FIELD_PRIORITY = ['name', 'label', 'title'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private FilterMetadataProvider $filterMetadataProvider,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('entityClass', InputArgument::OPTIONAL, 'Entity class name (short or full)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Show all Doctrine entities, not just #[Admin] ones')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entityClass = $input->getArgument('entityClass');
        $showAll = $input->getOption('all');
        $verbose = $io->isVerbose();

        if (!$entityClass) {
            $this->listEntities($io, $showAll, $verbose);
        } else {
            $this->showEntityFilters($io, $entityClass, $verbose);
        }

        return Command::SUCCESS;
    }

    private function listEntities(SymfonyStyle $io, bool $showAll, bool $verbose): void
    {
        $allMetadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        $entities = [];
        foreach ($allMetadata as $metadata) {
            $className = $metadata->getName();
            $reflection = new ReflectionClass($className);

            $isAdmin = !empty($reflection->getAttributes(Admin::class));

            if ($showAll || $isAdmin) {
                $entities[] = [
                    'class' => $className,
                    'shortName' => $reflection->getShortName(),
                    'isAdmin' => $isAdmin,
                ];
            }
        }

        if (empty($entities)) {
            $io->warning('No entities found. Use --all to show all Doctrine entities.');
            return;
        }

        $io->title('Admin Entities');

        $tableData = [];
        foreach ($entities as $entity) {
            $tableData[] = [
                $entity['shortName'],
                $entity['class'],
                $entity['isAdmin'] ? '<info>Yes</info>' : 'No',
            ];
        }

        $io->table(['Short Name', 'Full Class', '#[Admin]'], $tableData);

        $shortNames = array_column($entities, 'shortName');
        $selectedClass = $io->choice('Select an entity to inspect:', $shortNames);
        $this->showEntityFilters($io, $selectedClass, $verbose);
    }

    private function showEntityFilters(SymfonyStyle $io, string $entityClass, bool $verbose): void
    {
        $fullClass = $this->resolveEntityClass($entityClass);

        if ($fullClass === null) {
            $io->error(sprintf('Entity "%s" not found.', $entityClass));
            return;
        }

        $io->title(sprintf('Filter Metadata for %s', $fullClass));

        try {
            $filters = $this->filterMetadataProvider->getFilters($fullClass);
        } catch (\Exception $e) {
            $io->error(sprintf('Error getting filters: %s', $e->getMessage()));
            return;
        }

        if (empty($filters)) {
            $io->warning('No filters configured for this entity.');
            if ($verbose) {
                $io->text('Possible reasons:');
                $io->listing([
                    'Entity has no properties with #[ORM\\Column] or associations',
                    'All properties have #[ColumnFilter(enabled: false)]',
                    'Entity is not a valid Doctrine entity',
                ]);
            }
            return;
        }

        $metadata = $this->entityManager->getClassMetadata($fullClass);

        foreach ($filters as $column => $config) {
            $this->printFilterConfig($io, $column, $config, $metadata, $fullClass, $verbose);
        }

        // Show skipped properties in verbose mode
        if ($verbose) {
            $this->showSkippedProperties($io, $fullClass, $filters);
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param ClassMetadata<object> $metadata
     * @param class-string $entityClass
     */
    private function printFilterConfig(
        SymfonyStyle $io,
        string $column,
        array $config,
        ClassMetadata $metadata,
        string $entityClass,
        bool $verbose
    ): void {
        $type = $config['type'] ?? 'unknown';
        $enabled = $config['enabled'] ?? true;

        $io->section(sprintf('%s (%s)%s', $column, $type, $enabled ? '' : ' [DISABLED]'));

        if ($verbose) {
            $this->explainTypeDetection($io, $column, $config, $metadata, $entityClass);
        }

        $details = [];

        // Common fields
        if (isset($config['operator'])) {
            $details[] = ['Operator', $config['operator']];
        }
        if (isset($config['label'])) {
            $details[] = ['Label', $config['label']];
        }
        if (isset($config['placeholder'])) {
            $details[] = ['Placeholder', $config['placeholder']];
        }
        if (isset($config['priority'])) {
            $details[] = ['Priority', (string) $config['priority']];
        }

        // Enum-specific
        if ($type === 'enum') {
            if (isset($config['enumClass'])) {
                $details[] = ['Enum Class', $config['enumClass']];
            }
            $details[] = ['Multiple', ($config['multiple'] ?? false) ? 'Yes' : 'No'];
            $details[] = ['Show All Option', ($config['showAllOption'] ?? true) ? 'Yes' : 'No'];
        }

        // Relation-specific
        if ($type === 'relation') {
            if (isset($config['targetEntity'])) {
                $details[] = ['Target Entity', $config['targetEntity']];
            }
            if (isset($config['targetClass'])) {
                $details[] = ['Target Class', $config['targetClass']];
            }
            if (isset($config['searchFields'])) {
                $searchFields = $config['searchFields'];
                $details[] = [
                    '<options=bold>Search Fields</>',
                    sprintf('<info>%s</info>', implode(', ', $searchFields)),
                ];

                if ($verbose) {
                    $this->explainSearchFieldDetection($io, $config, $metadata, $column);
                }
            } else {
                $details[] = ['Search Fields', '<comment>Not configured (will fallback to id)</comment>'];
            }
        }

        if (!empty($details)) {
            $io->table([], $details);
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param ClassMetadata<object> $metadata
     * @param class-string $entityClass
     */
    private function explainTypeDetection(
        SymfonyStyle $io,
        string $column,
        array $config,
        ClassMetadata $metadata,
        string $entityClass
    ): void {
        $type = $config['type'] ?? 'unknown';
        $reasons = [];

        // Check for ColumnFilter attribute
        $reflection = new ReflectionClass($entityClass);
        $hasAttribute = false;
        $attributeType = null;

        if ($reflection->hasProperty($column)) {
            $property = $reflection->getProperty($column);
            $attributes = $property->getAttributes(ColumnFilter::class);
            if (!empty($attributes)) {
                $hasAttribute = true;
                $instance = $attributes[0]->newInstance();
                $attributeType = $instance->type;
            }
        }

        if ($hasAttribute && $attributeType !== null) {
            $reasons[] = sprintf(
                '<info>✓</info> Type explicitly set via #[ColumnFilter(type: "%s")]',
                $attributeType
            );
        } elseif ($metadata->hasAssociation($column)) {
            $assocType = $metadata->getAssociationMapping($column)['type'] ?? 0;
            $assocTypeName = match ($assocType) {
                ClassMetadata::ONE_TO_ONE => 'OneToOne',
                ClassMetadata::MANY_TO_ONE => 'ManyToOne',
                ClassMetadata::ONE_TO_MANY => 'OneToMany',
                ClassMetadata::MANY_TO_MANY => 'ManyToMany',
                default => 'Unknown',
            };
            $reasons[] = sprintf(
                '<info>✓</info> Detected as relation (Doctrine %s association)',
                $assocTypeName
            );
        } elseif ($metadata->hasField($column)) {
            $doctrineType = $metadata->getTypeOfField($column);
            $reasons[] = sprintf(
                '<info>✓</info> Doctrine field type "%s" → filter type "%s"',
                $doctrineType,
                $type
            );

            // Check for PHP enum
            if ($type === 'enum' && isset($config['enumClass'])) {
                $reasons[] = sprintf(
                    '<info>✓</info> Property type is PHP enum: %s',
                    $config['enumClass']
                );
            }
        }

        if ($hasAttribute) {
            $reasons[] = '<info>✓</info> Has #[ColumnFilter] attribute';
        } else {
            $reasons[] = '<comment>○</comment> No #[ColumnFilter] attribute (using auto-detection)';
        }

        $io->text('Type detection:');
        foreach ($reasons as $reason) {
            $io->text('  ' . $reason);
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param ClassMetadata<object> $metadata
     */
    private function explainSearchFieldDetection(
        SymfonyStyle $io,
        array $config,
        ClassMetadata $metadata,
        string $column
    ): void {
        if (!isset($config['targetClass'])) {
            return;
        }

        $targetClass = $config['targetClass'];
        $targetMetadata = $this->entityManager->getClassMetadata($targetClass);
        $targetFields = $targetMetadata->getFieldNames();
        $searchFields = $config['searchFields'] ?? [];

        $io->newLine();
        $io->text('<options=bold>Search field detection:</options>');

        // Check if explicitly configured
        $reflection = new ReflectionClass($metadata->getName());
        if ($reflection->hasProperty($column)) {
            $property = $reflection->getProperty($column);
            $attributes = $property->getAttributes(ColumnFilter::class);
            if (!empty($attributes)) {
                $instance = $attributes[0]->newInstance();
                if (!empty($instance->searchFields)) {
                    $io->text(sprintf(
                        '  <info>✓</info> Explicitly set via #[ColumnFilter(searchFields: ["%s"])]',
                        implode('", "', $instance->searchFields)
                    ));
                    return;
                }
            }
        }

        // Show auto-detection process
        $io->text(sprintf(
            '  <comment>○</comment> No explicit searchFields, checking target entity "%s"',
            (new ReflectionClass($targetClass))->getShortName()
        ));
        $io->text(sprintf('  <comment>○</comment> Available fields: %s', implode(', ', $targetFields)));

        foreach (self::DISPLAY_FIELD_PRIORITY as $priorityField) {
            if (in_array($priorityField, $targetFields, true)) {
                $io->text(sprintf(
                    '  <info>✓</info> Found "%s" in priority list [%s] → using it',
                    $priorityField,
                    implode(', ', self::DISPLAY_FIELD_PRIORITY)
                ));
                return;
            } else {
                $io->text(sprintf(
                    '  <comment>○</comment> Field "%s" not found in target entity',
                    $priorityField
                ));
            }
        }

        // Fallback to id
        if ($searchFields === ['id']) {
            $io->text('  <comment>!</comment> No priority field found → falling back to "id"');
        }
    }

    /**
     * @param class-string $entityClass
     * @param array<string, array<string, mixed>> $configuredFilters
     */
    private function showSkippedProperties(SymfonyStyle $io, string $entityClass, array $configuredFilters): void
    {
        $reflection = new ReflectionClass($entityClass);
        $metadata = $this->entityManager->getClassMetadata($entityClass);

        $allProperties = $reflection->getProperties();
        $skipped = [];

        foreach ($allProperties as $property) {
            $propertyName = $property->getName();

            // Skip if already configured
            if (isset($configuredFilters[$propertyName])) {
                continue;
            }

            $reasons = [];

            // Check various skip reasons
            if (!$metadata->hasField($propertyName) && !$metadata->hasAssociation($propertyName)) {
                $reasons[] = 'Not a Doctrine field or association';
            }

            // Check for disabled attribute
            $attributes = $property->getAttributes(ColumnFilter::class);
            if (!empty($attributes)) {
                $instance = $attributes[0]->newInstance();
                if (!$instance->enabled) {
                    $reasons[] = '#[ColumnFilter(enabled: false)]';
                }
            }

            // Check for OneToMany/ManyToMany (collections, not filterable by default)
            if ($metadata->hasAssociation($propertyName)) {
                $assocType = $metadata->getAssociationMapping($propertyName)['type'] ?? 0;
                if ($assocType === ClassMetadata::ONE_TO_MANY || $assocType === ClassMetadata::MANY_TO_MANY) {
                    $reasons[] = 'Collection association (OneToMany/ManyToMany) - not filterable';
                }
            }

            if (!empty($reasons)) {
                $skipped[$propertyName] = $reasons;
            }
        }

        if (!empty($skipped)) {
            $io->section('Skipped Properties');
            foreach ($skipped as $propertyName => $reasons) {
                $io->text(sprintf('<comment>%s</comment>:', $propertyName));
                foreach ($reasons as $reason) {
                    $io->text(sprintf('  <comment>○</comment> %s', $reason));
                }
            }
        }
    }

    /**
     * @return class-string|null
     */
    private function resolveEntityClass(string $entityClass): ?string
    {
        // If it's already a full class name
        if (class_exists($entityClass)) {
            return $entityClass;
        }

        // Try to find by short name
        $allMetadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        foreach ($allMetadata as $metadata) {
            $className = $metadata->getName();
            $reflection = new ReflectionClass($className);

            if ($reflection->getShortName() === $entityClass) {
                return $className;
            }
        }

        // Try common namespace patterns
        $commonNamespaces = ['App\\Entity\\', 'App\\Domain\\Entity\\'];
        foreach ($commonNamespaces as $namespace) {
            $fullClass = $namespace . $entityClass;
            if (class_exists($fullClass)) {
                return $fullClass;
            }
        }

        return null;
    }
}
