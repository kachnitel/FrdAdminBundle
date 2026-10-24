<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional\Form;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Kachnitel\AdminBundle\Tests\Fixtures\ReadOnlyFormEntity;
use Kachnitel\AdminBundle\Tests\Functional\TestKernel;
use Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Proves #[AdminColumn(editable: false, readOnlyInForm: true)] works end to end
 * through the real container: the field is rendered, disabled, and a submitted
 * value cannot overwrite the entity.
 */
#[Group('readonly-form')]
#[Group('dynamic-form')]
final class ReadOnlyFormFieldIntegrationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();

        $em = $this->getEm();
        (new SchemaTool($em))->updateSchema([$em->getClassMetadata(ReadOnlyFormEntity::class)]);
    }

    protected function tearDown(): void
    {
        $em = $this->getEm();
        $em->getConnection()->executeStatement('DELETE FROM test_read_only_form');
        $em->clear();

        parent::tearDown();
    }

    public function testReadOnlyInFormFieldIsPresentAndDisabled(): void
    {
        $form = $this->createForm(new ReadOnlyFormEntity());

        $this->assertTrue($form->has('code'));
        $this->assertTrue($form->get('code')->isDisabled());
        $this->assertSame('ORIGINAL', $form->get('code')->getData());
    }

    public function testOrdinaryFieldRemainsEnabled(): void
    {
        $form = $this->createForm(new ReadOnlyFormEntity());

        $this->assertTrue($form->has('title'));
        $this->assertFalse($form->get('title')->isDisabled());
    }

    public function testEditableFalseWithoutOptInIsStillAbsent(): void
    {
        $this->assertFalse($this->createForm(new ReadOnlyFormEntity())->has('hiddenCode'));
    }

    public function testSubmittedValueCannotOverwriteReadOnlyField(): void
    {
        $entity = new ReadOnlyFormEntity();
        $form   = $this->createForm($entity);

        $form->submit(['title' => 'New title', 'code' => 'TAMPERED']);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('New title', $entity->getTitle());
        $this->assertSame('ORIGINAL', $entity->getCode());
    }

    /** @return FormInterface<ReadOnlyFormEntity> */
    private function createForm(ReadOnlyFormEntity $entity): FormInterface
    {
        /** @var FormFactoryInterface $factory */
        $factory = static::getContainer()->get('form.factory');

        /** @var FormInterface<ReadOnlyFormEntity> */
        return $factory->create(DynamicEntityFormType::class, $entity, [
            'entity_class'    => ReadOnlyFormEntity::class,
            'data_class'      => ReadOnlyFormEntity::class,
            'csrf_protection' => false,
            'is_root'         => true,
        ]);
    }

    private function getEm(): EntityManagerInterface
    {
        /** @var ManagerRegistry $doctrine */
        $doctrine = static::getContainer()->get('doctrine');
        /** @var EntityManager */
        return $doctrine->getManager();
    }
}
