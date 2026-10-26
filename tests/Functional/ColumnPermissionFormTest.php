<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\ColumnPermissionFormEntity;
use Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Group('column-permission-form')]
final class ColumnPermissionFormTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testDeniedShowFieldIsRemovedFromGeneratedForm(): void
    {
        self::bootKernel();
        $this->setRoles([]);

        $form = $this->createForm(new ColumnPermissionFormEntity());

        self::assertFalse($form->has('salary'));
        self::assertFalse($form->has('ssn'));
        self::assertTrue($form->has('name'));
    }

    public function testEditDeniedFieldIsDisabledAndTamperedSubmissionIsIgnored(): void
    {
        self::bootKernel();
        $this->setRoles(['ROLE_HR']);
        $entity = (new ColumnPermissionFormEntity())
            ->setName('Original name')
            ->setSalary('100000');

        $form = $this->createForm($entity);

        self::assertTrue($form->has('salary'));
        self::assertTrue($form->get('salary')->isDisabled());
        self::assertTrue($form->has('ssn'));

        $form->submit([
            'name' => 'Updated name',
            'salary' => 'tampered salary',
            'ssn' => '123-45-6789',
        ]);

        self::assertSame('100000', $entity->getSalary());
        self::assertSame('123-45-6789', $entity->getSsn());
    }
    public function testSubmittedValueForRemovedFieldIsRejected(): void
    {
        self::bootKernel();
        $this->setRoles([]);
        $entity = (new ColumnPermissionFormEntity())
            ->setName('Original name')
            ->setSalary('100000')
            ->setSsn('original ssn');
        $form = $this->createForm($entity);

        self::assertFalse($form->has('ssn'));
        $form->submit([
            'name' => 'Updated name',
            'ssn' => 'tampered ssn',
        ]);

        self::assertFalse($form->isValid());
        self::assertSame('original ssn', $entity->getSsn());
    }

    /** @return FormInterface<ColumnPermissionFormEntity> */
    private function createForm(ColumnPermissionFormEntity $entity): FormInterface
    {
        /** @var FormFactoryInterface $factory */
        $factory = self::getContainer()->get('form.factory');

        /** @var FormInterface<ColumnPermissionFormEntity> $form */
        $form = $factory->create(DynamicEntityFormType::class, $entity, [
            'entity_class' => ColumnPermissionFormEntity::class,
            'data_class' => ColumnPermissionFormEntity::class,
            'entity_instance' => $entity,
            'is_root' => true,
            'csrf_protection' => false,
        ]);

        return $form;
    }

    /** @param list<string> $roles */
    private function setRoles(array $roles): void
    {
        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = self::getContainer()->get('security.token_storage');
        $tokenStorage->setToken(new UsernamePasswordToken(
            new InMemoryUser('test', null, $roles),
            'main',
            $roles,
        ));
    }
}