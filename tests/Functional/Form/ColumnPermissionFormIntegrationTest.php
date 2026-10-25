<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional\Form;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Kachnitel\AdminBundle\Tests\Fixtures\FormPermissionEntity;
use Kachnitel\AdminBundle\Tests\Functional\TestKernel;
use Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * #[ColumnPermission] drives the New/Edit form through the real container:
 *   no ADMIN_SHOW role → no entry; ADMIN_SHOW only → read-only (disabled);
 *   ADMIN_SHOW + ADMIN_EDIT → read-write. #[AdminColumn(editable: false)] can only
 *   restrict further: such a column is read-only for everyone who may see it.
 */
#[Group('column-permission-form')]
#[Group('dynamic-form')]
final class ColumnPermissionFormIntegrationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();

        $em = $this->getEm();
        (new SchemaTool($em))->updateSchema([$em->getClassMetadata(FormPermissionEntity::class)]);
    }

    protected function tearDown(): void
    {
        $em = $this->getEm();
        $em->getConnection()->executeStatement('DELETE FROM test_form_permission');
        $em->clear();

        parent::tearDown();
    }

    public function testColumnWithoutShowRoleHasNoEntry(): void
    {
        $form = $this->createForm(new FormPermissionEntity(), []);

        $this->assertFalse($form->has('salary'));
        $this->assertTrue($form->has('title'));
    }

    public function testColumnWithShowRoleOnlyIsDisabled(): void
    {
        $form = $this->createForm(new FormPermissionEntity(), ['ROLE_HR']);

        $this->assertTrue($form->has('salary'));
        $this->assertTrue($form->get('salary')->isDisabled());
        $this->assertFalse($form->get('title')->isDisabled());
    }

    public function testColumnWithShowAndEditRolesIsEnabled(): void
    {
        $form = $this->createForm(new FormPermissionEntity(), ['ROLE_HR', 'ROLE_HR_EDIT']);

        $this->assertTrue($form->has('salary'));
        $this->assertFalse($form->get('salary')->isDisabled());
    }

    public function testReadOnlyColumnCannotBeOverwrittenBySubmit(): void
    {
        $entity = new FormPermissionEntity();
        $form   = $this->createForm($entity, ['ROLE_HR']);

        $form->submit(['title' => 'T', 'salary' => '999999']);

        $this->assertSame('T', $entity->getTitle());
        $this->assertSame('100', $entity->getSalary());
    }

    public function testEditableFalseColumnIsReadOnlyEvenWithEveryRole(): void
    {
        $entity = new FormPermissionEntity();
        $form   = $this->createForm($entity, ['ROLE_HR', 'ROLE_HR_EDIT']);

        $this->assertTrue($form->has('lockedNote'));
        $this->assertTrue($form->get('lockedNote')->isDisabled());

        $form->submit(['title' => 'T', 'salary' => '5', 'lockedNote' => 'TAMPERED']);

        $this->assertSame('', $entity->getLockedNote());
    }

    /**
     * @param list<string> $roles
     * @return FormInterface<FormPermissionEntity>
     */
    private function createForm(FormPermissionEntity $entity, array $roles): FormInterface
    {
        /** @var TokenStorageInterface $tokens */
        $tokens = static::getContainer()->get('security.token_storage');
        $user   = new InMemoryUser('form_user', 'password', $roles);
        $tokens->setToken(new UsernamePasswordToken($user, 'main', $roles));

        /** @var FormFactoryInterface $factory */
        $factory = static::getContainer()->get('form.factory');

        /** @var FormInterface<FormPermissionEntity> */
        return $factory->create(DynamicEntityFormType::class, $entity, [
            'entity_class'    => FormPermissionEntity::class,
            'data_class'      => FormPermissionEntity::class,
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
