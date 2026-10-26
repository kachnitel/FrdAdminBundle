<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Kachnitel\AdminBundle\Tests\Fixtures\ColumnPermissionFormEntity;
use Kachnitel\AdminBundle\Twig\Runtime\AdminEntityDataRuntime;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[Group('column-permission-show')]
final class ColumnPermissionShowPageTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    protected static function getKernelClass(): string
    {
        return NoAuthTestKernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $entityManager = $this->client->getContainer()->get('doctrine')->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->em = $entityManager;

        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testDeniedShowColumnAndHeaderAreAbsent(): void
    {
        $entity = (new ColumnPermissionFormEntity())
            ->setName('Visible employee')
            ->setSalary('secret salary 98765')
            ->setSsn('secret ssn 123-45-6789');
        $this->em->persist($entity);
        $this->em->flush();

        $this->client->request('GET', '/admin/column-permission-form-entity/' . $entity->getId());

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Visible employee', $content);
        self::assertStringNotContainsString('secret salary 98765', $content);
        self::assertStringNotContainsString('secret ssn 123-45-6789', $content);
        self::assertStringNotContainsString('Salary', $content);
        self::assertStringNotContainsString('Ssn', $content);
    }

    public function testAllowedUserCanSeeProtectedColumnValues(): void
    {
        $entity = (new ColumnPermissionFormEntity())
            ->setName('Visible employee')
            ->setSalary('salary visible 98765')
            ->setSsn('ssn visible 123-45-6789');
        $this->em->persist($entity);
        $this->em->flush();

        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->client->getContainer()->get('security.token_storage');
        $roles = ['ROLE_HR'];
        $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('hr', null, $roles), 'main', $roles));

        /** @var AdminEntityDataRuntime $runtime */
        $runtime = $this->client->getContainer()->get(AdminEntityDataRuntime::class);
        $data = $runtime->getData($entity);

        self::assertSame('salary visible 98765', $data['salary']);
        self::assertSame('ssn visible 123-45-6789', $data['ssn']);
    }
}