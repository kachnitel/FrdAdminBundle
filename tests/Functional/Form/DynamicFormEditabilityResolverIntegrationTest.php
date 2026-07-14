<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional\Form;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Kachnitel\AdminBundle\Tests\Fixtures\OrderLineItem;
use Kachnitel\AdminBundle\Tests\Fixtures\OrderWithLines;
use Kachnitel\AdminBundle\Tests\Fixtures\TagFixture;
use Kachnitel\AdminBundle\Tests\Functional\TestKernel;
use Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * Proves AdminColumnEditabilityResolver is actually wired in as the live
 * FieldEditabilityResolverInterface binding in this application's container —
 * i.e. that #[AdminColumn(editable: false)] genuinely excludes a collection
 * from a DynamicEntityFormType built by the real container, not just against
 * AdminColumnEditabilityResolverTest's mocked collaborators.
 *
 * Replaces the one case from the pre-extraction DynamicFormCollectionTest that
 * couldn't move to dynamic-form-bundle wholesale with the rest of that file:
 * that bundle's own kernel wires AlwaysEditableFieldResolver, which has no
 * knowledge of #[AdminColumn] at all and so can never prove this exclusion —
 * only admin-bundle's kernel has the real alias override.
 *
 * @group dynamic-form
 * @group collections
 * @group editability
 */
final class DynamicFormEditabilityResolverIntegrationTest extends KernelTestCase
{
    /** @var array<class-string> */
    private const FIXTURE_CLASSES = [
        TagFixture::class,
        OrderWithLines::class,
        OrderLineItem::class,
    ];

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();

        $em         = $this->getEm();
        $schemaTool = new SchemaTool($em);
        $classes    = array_map(
            fn (string $class) => $em->getClassMetadata($class),
            self::FIXTURE_CLASSES,
        );
        $schemaTool->updateSchema($classes);
    }

    protected function tearDown(): void
    {
        $em         = $this->getEm();
        $connection = $em->getConnection();

        $connection->executeStatement('DELETE FROM test_order_tags');
        $connection->executeStatement('DELETE FROM test_order_blocked_tags');
        $connection->executeStatement('DELETE FROM test_order_line_item');
        $connection->executeStatement('DELETE FROM test_order_with_lines');
        $connection->executeStatement('DELETE FROM test_tag_fixture');

        $em->clear();

        parent::tearDown();
    }

    public function testEditableFalseCollectionIsAbsentFromRootFormViaTheRealContainerBinding(): void
    {
        $form = $this->getFormFactory()->create(DynamicEntityFormType::class, new OrderWithLines(), [
            'entity_class'    => OrderWithLines::class,
            'data_class'      => OrderWithLines::class,
            'csrf_protection' => false,
            'is_root'         => true,
        ]);

        $this->assertFalse(
            $form->has('blockedTags'),
            'AdminColumnEditabilityResolver must be the live FieldEditabilityResolverInterface '
            . 'binding in this application — excluding #[AdminColumn(editable: false)] collections '
            . 'exactly as DynamicEntityFormType did before the dynamic-form-bundle extraction.'
        );
    }

    /**
     * Paired with the above: a sibling ManyToMany with no #[AdminColumn] override
     * must still be included, proving the resolver's permissive default is live
     * through the real container too, not just its false-blocking path.
     */
    public function testUnannotatedCollectionIsStillIncludedInRootForm(): void
    {
        $form = $this->getFormFactory()->create(DynamicEntityFormType::class, new OrderWithLines(), [
            'entity_class'    => OrderWithLines::class,
            'data_class'      => OrderWithLines::class,
            'csrf_protection' => false,
            'is_root'         => true,
        ]);

        $this->assertTrue($form->has('tags'));
    }

    private function getFormFactory(): FormFactoryInterface
    {
        /** @var FormFactoryInterface */
        return static::getContainer()->get('form.factory');
    }

    private function getEm(): EntityManagerInterface
    {
        /** @var ManagerRegistry $doctrine */
        $doctrine = static::getContainer()->get('doctrine');
        /** @var EntityManager */
        return $doctrine->getManager();
    }
}
