<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Command;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\ColumnFilter;
use Kachnitel\AdminBundle\Command\DebugFiltersCommand;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use Kachnitel\AdminBundle\Utils\PropertyReflection;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

#[PHPUnit\CoversClass(DebugFiltersCommand::class)]
#[PHPUnit\UsesClass(ColumnFilter::class)]
#[PHPUnit\UsesClass(PropertyReflection::class)]
#[PHPUnit\Group('debug-filters')]
final class DebugFiltersCommandTest extends TestCase
{
    private const VERBOSE = ['verbosity' => OutputInterface::VERBOSITY_VERBOSE];

    // ── listEntities() ────────────────────────────────────────────────────────

    #[PHPUnit\Test]
    public function listWarnsWhenNoAdminEntitiesExist(): void
    {
        $tester = $this->makeTester(allMetadata: [new ClassMetadata(DebugFiltersPlainEntity::class)]);

        $status = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('No entities found. Use --all', $tester->getDisplay());
    }

    #[PHPUnit\Test]
    public function listWithAllOptionIncludesNonAdminEntities(): void
    {
        $tester = $this->makeTester(allMetadata: [
            new ClassMetadata(DebugFiltersPlainEntity::class),
            $this->adminMetadata(),
        ]);
        $tester->setInputs(['0']);

        $tester->execute(['--all' => true]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('DebugFiltersPlainEntity', $display);
        $this->assertStringContainsString('DebugFiltersAdminEntity', $display);
        $this->assertStringContainsString('Yes', $display);
        $this->assertStringContainsString('No', $display);
    }

    #[PHPUnit\Test]
    public function listPromptsForEntityAndShowsItsFilters(): void
    {
        $tester = $this->makeTester(
            filters: ['title' => ['type' => 'text', 'operator' => 'LIKE']],
        );
        $tester->setInputs(['0']);

        $status = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $status);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Select an entity to inspect', $display);
        $this->assertStringContainsString('Filter Metadata for ' . DebugFiltersAdminEntity::class, $display);
        $this->assertStringContainsString('title (text)', $display);
    }

    // ── Entity resolution ─────────────────────────────────────────────────────

    #[PHPUnit\Test]
    public function reportsErrorWhenEntityCannotBeResolved(): void
    {
        $tester = $this->makeTester();

        $tester->execute(['entityClass' => 'DoesNotExist']);

        $this->assertStringContainsString('Entity "DoesNotExist" not found.', $tester->getDisplay());
    }

    #[PHPUnit\Test]
    public function resolvesFullyQualifiedClassName(): void
    {
        $tester = $this->makeTester();

        $tester->execute(['entityClass' => DebugFiltersAdminEntity::class]);

        $this->assertStringContainsString('Filter Metadata for ' . DebugFiltersAdminEntity::class, $tester->getDisplay());
    }

    #[PHPUnit\Test]
    public function resolvesViaCommonNamespaces(): void
    {
        if (!class_exists('App\\Entity\\DebugFiltersAliased', false)) {
            class_alias(DebugFiltersAdminEntity::class, 'App\\Entity\\DebugFiltersAliased');
        }
        if (!class_exists('App\\Domain\\Entity\\DebugFiltersAliasedDomain', false)) {
            class_alias(DebugFiltersAdminEntity::class, 'App\\Domain\\Entity\\DebugFiltersAliasedDomain');
        }

        $tester = $this->makeTester(allMetadata: []);

        $tester->execute(['entityClass' => 'DebugFiltersAliased']);
        $this->assertStringContainsString('Filter Metadata for App\\Entity\\DebugFiltersAliased', $tester->getDisplay());

        $tester->execute(['entityClass' => 'DebugFiltersAliasedDomain']);
        $this->assertStringContainsString('Filter Metadata for App\\Domain\\Entity\\DebugFiltersAliasedDomain', $tester->getDisplay());
    }

    // ── showEntityFilters() ───────────────────────────────────────────────────

    #[PHPUnit\Test]
    public function reportsErrorWhenFilterProviderThrows(): void
    {
        $tester = $this->makeTester(filterError: new \RuntimeException('boom'));

        $tester->execute(['entityClass' => DebugFiltersAdminEntity::class]);

        $this->assertStringContainsString('Error getting filters: boom', $tester->getDisplay());
    }

    #[PHPUnit\Test]
    public function warnsWhenEntityHasNoFilters(): void
    {
        $tester = $this->makeTester(filters: []);

        $tester->execute(['entityClass' => DebugFiltersAdminEntity::class]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('No filters configured for this entity.', $display);
        $this->assertStringNotContainsString('Possible reasons', $display);
    }

    #[PHPUnit\Test]
    public function verboseNoFiltersListsPossibleReasons(): void
    {
        $tester = $this->makeTester(filters: []);

        $tester->execute(['entityClass' => DebugFiltersAdminEntity::class], self::VERBOSE);

        $this->assertStringContainsString('Possible reasons', $tester->getDisplay());
    }

    // ── printFilterConfig(): common + type-specific details (non-verbose) ────

    #[PHPUnit\Test]
    public function printsCommonFilterDetailsAndDisabledMarker(): void
    {
        $tester = $this->makeTester(filters: [
            'title' => [
                'type' => 'text',
                'operator' => 'LIKE',
                'label' => 'Title label',
                'placeholder' => 'Search…',
                'priority' => 3,
                'enabled' => false,
            ],
        ]);

        $tester->execute(['entityClass' => DebugFiltersAdminEntity::class]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('title (text) [DISABLED]', $display);
        $this->assertStringContainsString('LIKE', $display);
        $this->assertStringContainsString('Title label', $display);
        $this->assertStringContainsString('Search…', $display);
        $this->assertStringContainsString('Priority', $display);
    }

    #[PHPUnit\Test]
    public function nonScalarDetailValuesAreStringifiedAsEmpty(): void
    {
        $tester = $this->makeTester(filters: [
            'title' => ['type' => 'text', 'label' => ['not', 'scalar']],
        ]);

        $tester->execute(['entityClass' => DebugFiltersAdminEntity::class]);

        $this->assertStringContainsString('Label', $tester->getDisplay());
    }

    #[PHPUnit\Test]
    public function filterWithoutTypeOrDetailsIsLabelledUnknown(): void
    {
        $tester = $this->makeTester(filters: ['title' => []]);

        $tester->execute(['entityClass' => DebugFiltersAdminEntity::class]);

        $this->assertStringContainsString('title (unknown)', $tester->getDisplay());
    }

    #[PHPUnit\Test]
    public function printsEnumDetails(): void
    {
        $tester = $this->makeTester(filters: [
            'a' => ['type' => 'enum', 'enumClass' => 'App\\Enum\\Status', 'multiple' => true, 'showAllOption' => false],
            'b' => ['type' => 'enum'],
        ]);

        $tester->execute(['entityClass' => DebugFiltersAdminEntity::class]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('App\\Enum\\Status', $display);
        $this->assertStringContainsString('Multiple', $display);
        $this->assertStringContainsString('Show All Option', $display);
    }

    #[PHPUnit\Test]
    public function printsRelationDetails(): void
    {
        $tester = $this->makeTester(filters: [
            'owner' => [
                'type' => 'relation',
                'targetEntity' => 'DebugFiltersTarget',
                'targetClass' => DebugFiltersTarget::class,
                'searchFields' => ['name', 5],
            ],
            'noSearch' => ['type' => 'relation'],
            'badSearch' => ['type' => 'relation', 'searchFields' => 'not-an-array'],
        ]);

        $tester->execute(['entityClass' => DebugFiltersAdminEntity::class]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Target Entity', $display);
        $this->assertStringContainsString('Target Class', $display);
        $this->assertStringContainsString('Search Fields', $display);
        $this->assertStringContainsString('Not configured (will fallback to id)', $display);
    }

    // ── Verbose: type detection + search-field detection ─────────────────────

    #[PHPUnit\Test]
    public function verboseExplainsTypeDetectionForEveryBranch(): void
    {
        $tester = $this->makeTester(filters: [
            'title'          => ['type' => 'text'],
            'plain'          => ['type' => 'enum', 'enumClass' => 'App\\Enum\\Status'],
            'attrNoType'     => ['type' => 'text'],
            'notMapped'      => ['type' => 'text'],
            'ghost'          => ['type' => 'text'],
            'noAttrRelation' => ['type' => 'relation'],
            'profile'        => ['type' => 'relation'],
            'untaggedItems'  => ['type' => 'relation'],
            'tags'           => ['type' => 'collection'],
            'typeless'       => [],
        ]);

        $tester->execute(['entityClass' => DebugFiltersAdminEntity::class], self::VERBOSE);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Type explicitly set via #[ColumnFilter(type: "text")]', $display);
        $this->assertStringContainsString('Doctrine field type "string" → filter type "enum"', $display);
        $this->assertStringContainsString('Property type is PHP enum: App\\Enum\\Status', $display);
        $this->assertStringContainsString('No #[ColumnFilter] attribute (using auto-detection)', $display);
        $this->assertStringContainsString('Has #[ColumnFilter] attribute', $display);
        $this->assertStringContainsString('Doctrine ManyToOne association', $display);
        $this->assertStringContainsString('Doctrine OneToOne association', $display);
        $this->assertStringContainsString('Doctrine OneToMany association', $display);
        $this->assertStringContainsString('Doctrine ManyToMany association', $display);
    }

    #[PHPUnit\Test]
    public function associationTypeNameIsUnknownForUnrecognizedType(): void
    {
        $method = new \ReflectionMethod(DebugFiltersCommand::class, 'getAssociationTypeName');

        $this->assertSame('Unknown', $method->invoke(null, 0));
    }

    #[PHPUnit\Test]
    public function verboseExplainsSearchFieldDetection(): void
    {
        $tester = $this->makeTester(filters: [
            // explicit #[ColumnFilter(searchFields: [...])]
            'owner' => [
                'type' => 'relation',
                'targetClass' => DebugFiltersTarget::class,
                'searchFields' => ['label'],
            ],
            // no attribute → priority list hit ("name")
            'noAttrRelation' => [
                'type' => 'relation',
                'targetClass' => DebugFiltersTarget::class,
                'searchFields' => ['name'],
            ],
            // attribute present but searchFields empty → auto-detect
            'attrNoType' => [
                'type' => 'relation',
                'targetClass' => DebugFiltersTarget::class,
                'searchFields' => ['name'],
            ],
            // not a property of the entity → auto-detect
            'ghost' => [
                'type' => 'relation',
                'targetClass' => DebugFiltersTarget::class,
                'searchFields' => ['name'],
            ],
            // no priority field → falls back to id
            'profile' => [
                'type' => 'relation',
                'targetClass' => DebugFiltersNoPriorityTarget::class,
                'searchFields' => ['id'],
            ],
            // no priority field, searchFields not ['id']
            'untaggedItems' => [
                'type' => 'relation',
                'targetClass' => DebugFiltersNoPriorityTarget::class,
                'searchFields' => ['code'],
            ],
            // early returns: missing / non-string / unknown targetClass
            'missingTarget'   => ['type' => 'relation', 'searchFields' => ['x']],
            'nonStringTarget' => ['type' => 'relation', 'targetClass' => 5, 'searchFields' => ['x']],
            'badTarget'       => ['type' => 'relation', 'targetClass' => 'No\\Such\\Class', 'searchFields' => ['x']],
        ]);

        $tester->execute(['entityClass' => DebugFiltersAdminEntity::class], self::VERBOSE);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Explicitly set via #[ColumnFilter(searchFields: ["label"])]', $display);
        $this->assertStringContainsString('No explicit searchFields, checking target entity "DebugFiltersTarget"', $display);
        $this->assertStringContainsString('Found "name" in priority list', $display);
        $this->assertStringContainsString('Field "name" not found in target entity', $display);
        $this->assertStringContainsString('No priority field found → falling back to "id"', $display);
    }

    // ── Verbose: skipped properties ──────────────────────────────────────────

    #[PHPUnit\Test]
    public function verboseListsSkippedPropertiesWithReasons(): void
    {
        $tester = $this->makeTester(filters: ['title' => ['type' => 'text']]);

        $tester->execute(['entityClass' => DebugFiltersAdminEntity::class], self::VERBOSE);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Skipped Properties', $display);
        $this->assertStringContainsString('Not a Doctrine field or association', $display);
        $this->assertStringContainsString('#[ColumnFilter(enabled: false)]', $display);
        $this->assertStringContainsString('Collection association (OneToMany/ManyToMany) - add #[ColumnFilter] to enable', $display);
    }

    #[PHPUnit\Test]
    public function verboseOmitsSkippedSectionWhenEverythingIsConfigured(): void
    {
        $tester = $this->makeTester(filters: [
            'name'  => ['type' => 'text'],
            'label' => ['type' => 'text'],
        ]);

        $tester->execute(['entityClass' => DebugFiltersTarget::class], self::VERBOSE);

        $this->assertStringNotContainsString('Skipped Properties', $tester->getDisplay());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * @param array<string, array<string, mixed>> $filters
     * @param list<ClassMetadata<object>>|null    $allMetadata
     * @param array<string, ClassMetadata<object>> $metadataByClass
     */
    private function makeTester(
        array $filters = [],
        ?array $allMetadata = null,
        array $metadataByClass = [],
        ?\Throwable $filterError = null,
    ): CommandTester {
        $factory = $this->createStub(ClassMetadataFactory::class);
        $factory->method('getAllMetadata')->willReturn($allMetadata ?? [$this->adminMetadata()]);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($factory);
        $em->method('getClassMetadata')->willReturnCallback(
            fn (string $class): ClassMetadata => $metadataByClass[$class] ?? $this->metadataFor($class),
        );

        $provider = $this->createStub(FilterMetadataProvider::class);
        if ($filterError !== null) {
            $provider->method('getFilters')->willThrowException($filterError);
        } else {
            $provider->method('getFilters')->willReturn($filters);
        }

        return new CommandTester(new DebugFiltersCommand($em, $provider));
    }

    /**
     * @return ClassMetadata<object>
     */
    private function metadataFor(string $class): ClassMetadata
    {
        return match ($class) {
            DebugFiltersAdminEntity::class => $this->adminMetadata(),
            DebugFiltersTarget::class => $this->fieldsMetadata(DebugFiltersTarget::class, ['name', 'label']),
            DebugFiltersNoPriorityTarget::class => $this->fieldsMetadata(DebugFiltersNoPriorityTarget::class, ['code']),
            default => new ClassMetadata($class),
        };
    }

    /**
     * @param class-string  $class
     * @param list<string>  $fields
     * @return ClassMetadata<object>
     */
    private function fieldsMetadata(string $class, array $fields): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        foreach ($fields as $field) {
            $metadata->mapField(['fieldName' => $field, 'type' => 'string']);
        }

        return $metadata;
    }

    /**
     * @return ClassMetadata<object>
     */
    private function adminMetadata(): ClassMetadata
    {
        $metadata = $this->fieldsMetadata(DebugFiltersAdminEntity::class, ['title', 'plain', 'hidden', 'attrNoType']);

        $target = DebugFiltersTarget::class;
        $metadata->mapManyToOne(['fieldName' => 'owner', 'targetEntity' => $target]);
        $metadata->mapManyToOne(['fieldName' => 'noAttrRelation', 'targetEntity' => $target]);
        $metadata->mapOneToOne(['fieldName' => 'profile', 'targetEntity' => $target]);
        $metadata->mapManyToMany(['fieldName' => 'tags', 'targetEntity' => $target]);
        $metadata->mapOneToMany(['fieldName' => 'untaggedItems', 'targetEntity' => $target, 'mappedBy' => 'owner']);

        return $metadata;
    }
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

#[Admin(label: 'Fixture')]
final class DebugFiltersAdminEntity
{
    #[ColumnFilter(type: ColumnFilter::TYPE_TEXT)]
    public string $title = '';

    public string $plain = '';

    #[ColumnFilter(enabled: false)]
    public string $hidden = '';

    #[ColumnFilter]
    public string $attrNoType = '';

    /** Deliberately not mapped by Doctrine. */
    public string $notMapped = '';

    #[ColumnFilter(type: ColumnFilter::TYPE_RELATION, searchFields: ['label'])]
    public ?DebugFiltersTarget $owner = null;

    public ?DebugFiltersTarget $noAttrRelation = null;

    public ?DebugFiltersTarget $profile = null;

    /** @var Collection<int, DebugFiltersTarget>|null */
    #[ColumnFilter]
    public ?Collection $tags = null;

    /** @var Collection<int, DebugFiltersTarget>|null */
    public ?Collection $untaggedItems = null;
}

final class DebugFiltersPlainEntity {}

final class DebugFiltersTarget
{
    public string $name = '';

    public string $label = '';
}

final class DebugFiltersNoPriorityTarget
{
    public string $code = '';
}
