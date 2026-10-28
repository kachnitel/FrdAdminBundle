<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\ColumnPermissionParent;
use Kachnitel\AdminBundle\Tests\Fixtures\ColumnPermissionRelated;
use Kachnitel\AdminBundle\Tests\Fixtures\ColumnPermissionRow;
use Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * #[ColumnPermission] on associations and inside collection rows of the generated form.
 *
 * Complements ColumnPermissionFormTest (scalar columns on a top-level entity):
 *   - ManyToOne / ManyToMany properties are removed (SHOW) or disabled (EDIT)
 *   - collection rows (non-root DynamicEntityFormType) apply the CHILD class's own permissions
 *   - disabled fields ignore tampered submissions, including inside a row
 *
 * Structure-only: submissions use clearMissing=false so no choice query (and no schema) is needed.
 */
#[Group('column-permission-form')]
#[Group('collections')]
final class ColumnPermissionNestedFormTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Test]
    public function showDeniedAssociationsAreRemoved(): void
    {
        self::bootKernel();
        $this->setRoles([]);

        $form = $this->createForm(new ColumnPermissionParent());

        self::assertTrue($form->has('name'));
        self::assertTrue($form->has('rows'));
        self::assertFalse($form->has('owner'));
        self::assertFalse($form->has('reviewer'));
        self::assertFalse($form->has('hiddenTags'));
        self::assertFalse($form->has('lockedTags'));
    }

    #[Test]
    public function editDeniedAssociationsAreDisabledWhenShowIsAllowed(): void
    {
        self::bootKernel();
        $this->setRoles(['ROLE_HR']);

        $form = $this->createForm(new ColumnPermissionParent());

        self::assertTrue($form->has('owner'));
        self::assertFalse($form->get('owner')->isDisabled(), 'SHOW-only restriction must stay editable');
        self::assertTrue($form->has('hiddenTags'));
        self::assertFalse($form->get('hiddenTags')->isDisabled());

        self::assertTrue($form->has('reviewer'));
        self::assertTrue($form->get('reviewer')->isDisabled());
        self::assertTrue($form->has('lockedTags'));
        self::assertTrue($form->get('lockedTags')->isDisabled());
    }

    #[Test]
    public function fullyPermittedUserGetsEditableAssociations(): void
    {
        self::bootKernel();
        $this->setRoles(['ROLE_HR', 'ROLE_HR_EDIT']);

        $form = $this->createForm(new ColumnPermissionParent());

        foreach (['owner', 'reviewer', 'hiddenTags', 'lockedTags'] as $field) {
            self::assertTrue($form->has($field), $field);
            self::assertFalse($form->get($field)->isDisabled(), $field);
        }
    }

    #[Test]
    public function tamperedValueForDisabledAssociationIsIgnored(): void
    {
        self::bootKernel();
        $this->setRoles(['ROLE_HR']);
        $reviewer = (new ColumnPermissionRelated())->setName('Original reviewer');
        $parent = (new ColumnPermissionParent())->setName('Parent')->setReviewer($reviewer);

        $form = $this->createForm($parent);
        $form->submit(['reviewer' => '999'], false);

        self::assertSame($reviewer, $parent->getReviewer());
    }

    #[Test]
    public function collectionRowsApplyTheChildClassPermissions(): void
    {
        self::bootKernel();

        $this->setRoles([]);
        $row = $this->createForm($this->parentWithRow())->get('rows')->get('0');
        self::assertTrue($row->has('description'));
        self::assertFalse($row->has('budget'), 'ADMIN_SHOW denied on the child class removes the row field');

        $this->setRoles(['ROLE_HR']);
        $row = $this->createForm($this->parentWithRow())->get('rows')->get('0');
        self::assertTrue($row->has('budget'));
        self::assertTrue($row->get('budget')->isDisabled());

        $this->setRoles(['ROLE_HR', 'ROLE_HR_EDIT']);
        $row = $this->createForm($this->parentWithRow())->get('rows')->get('0');
        self::assertTrue($row->has('budget'));
        self::assertFalse($row->get('budget')->isDisabled());
    }

    #[Test]
    public function tamperedValueForDisabledRowFieldIsIgnoredWhileOthersStillSave(): void
    {
        self::bootKernel();
        $this->setRoles(['ROLE_HR']);
        $parent = $this->parentWithRow();

        $form = $this->createForm($parent);
        $form->submit(['rows' => ['0' => ['description' => 'Updated', 'budget' => 'tampered']]], false);

        $row = $parent->getRows()->first();
        self::assertInstanceOf(ColumnPermissionRow::class, $row);
        self::assertSame('Updated', $row->getDescription());
        self::assertSame('1000', $row->getBudget());
    }

    #[Test]
    public function submittedValueForRemovedRowFieldInvalidatesTheForm(): void
    {
        self::bootKernel();
        $this->setRoles([]);
        $parent = $this->parentWithRow();

        $form = $this->createForm($parent);
        $form->submit(['rows' => ['0' => ['description' => 'Updated', 'budget' => 'tampered']]], false);

        self::assertFalse($form->isValid());
        $row = $parent->getRows()->first();
        self::assertInstanceOf(ColumnPermissionRow::class, $row);
        self::assertSame('1000', $row->getBudget());
    }

    private function parentWithRow(): ColumnPermissionParent
    {
        $parent = (new ColumnPermissionParent())->setName('Parent');
        $parent->addRow((new ColumnPermissionRow())->setDescription('Row')->setBudget('1000'));

        return $parent;
    }

    /** @return FormInterface<ColumnPermissionParent> */
    private function createForm(ColumnPermissionParent $entity): FormInterface
    {
        /** @var FormFactoryInterface $factory */
        $factory = self::getContainer()->get('form.factory');

        /** @var FormInterface<ColumnPermissionParent> $form */
        $form = $factory->create(DynamicEntityFormType::class, $entity, [
            'entity_class' => ColumnPermissionParent::class,
            'data_class' => ColumnPermissionParent::class,
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
