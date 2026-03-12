<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Kachnitel\AdminBundle\Tests\Fixtures\BasicAdminEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithRowActions;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests that show and edit page headers render applicable entity actions,
 * excluding the action that corresponds to the current page.
 *
 * Uses NoAuthTestKernel (required_role: null) to avoid authentication complexity.
 *
 * Icon reference (show/edit pages always use plain navigation links):
 *   - Show action: 👀  (DefaultRowActionProvider, all entities)
 *   - Edit action: 🖊  (DefaultRowActionProvider plain link — ignoreComponent: true
 *                       means InlineEditButton is bypassed even for entities with
 *                       enableInlineEdit: true; the ✏️ Stimulus button is list-only)
 *
 * @group show-edit-row-actions
 */
class ShowEditPageRowActionsTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return NoAuthTestKernel::class;
    }

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        // createClient() boots the kernel — do NOT call bootKernel() separately
        $this->client = static::createClient();
        $this->em     = $this->client->getContainer()->get('doctrine')->getManager();

        $schemaTool = new SchemaTool($this->em);
        $metadata   = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    // -------------------------------------------------------------------------
    // Show page
    // -------------------------------------------------------------------------

    /**
     * Show page header must include the Edit row action as a plain navigation link.
     * The 🖊 icon comes from DefaultRowActionProvider; InlineEditButton (✏️) is
     * suppressed via ignoreComponent: true — it has no parent EntityList here.
     */
    public function testShowPageHeaderIncludesEditAction(): void
    {
        $entity = new BasicAdminEntity();
        $entity->setName('Header Test');
        $this->em->persist($entity);
        $this->em->flush();

        $this->client->request('GET', '/admin/basic-admin-entity/' . $entity->getId());

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();

        $this->assertStringContainsString('🖊', $content);
    }

    /**
     * Show page header must NOT include the Show row action (it is the current page).
     */
    public function testShowPageHeaderExcludesShowAction(): void
    {
        $entity = new BasicAdminEntity();
        $entity->setName('No Show Button');
        $this->em->persist($entity);
        $this->em->flush();

        $this->client->request('GET', '/admin/basic-admin-entity/' . $entity->getId());

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();

        $this->assertStringNotContainsString('👀', $content);
    }

    // -------------------------------------------------------------------------
    // Edit page
    // -------------------------------------------------------------------------

    /**
     * Edit page header must include the Show row action as a plain navigation link.
     */
    public function testEditPageHeaderIncludesShowAction(): void
    {
        $entity = new TestEntity();
        $entity->setName('Edit Header Test');
        $this->em->persist($entity);
        $this->em->flush();

        $this->client->request('GET', '/admin/test-entity/' . $entity->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();

        $this->assertStringContainsString('👀', $content);
    }

    /**
     * Edit page header must NOT include the Edit row action (it is the current page).
     * Neither 🖊 (DefaultRowActionProvider link) nor ✏️ (InlineEditButton) should appear.
     */
    public function testEditPageHeaderExcludesEditAction(): void
    {
        $entity = new TestEntity();
        $entity->setName('No Edit Button');
        $this->em->persist($entity);
        $this->em->flush();

        $this->client->request('GET', '/admin/test-entity/' . $entity->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();

        $this->assertStringNotContainsString('🖊', $content);
        $this->assertStringNotContainsString('✏️', $content);
    }

    // -------------------------------------------------------------------------
    // Custom row actions
    // -------------------------------------------------------------------------

    /**
     * Custom row actions visible for the entity instance appear on the show page header.
     *
     * EntityWithRowActions has an 'approve' action (condition: status == 'pending').
     */
    public function testShowPageHeaderIncludesCustomRowActions(): void
    {
        $entity = new EntityWithRowActions();
        $entity->setName('Pending Item');
        $entity->setStatus('pending');
        $this->em->persist($entity);
        $this->em->flush();

        $this->client->request('GET', '/admin/entity-with-row-actions/' . $entity->getId());

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();

        $this->assertStringContainsString('Approve', $content);
    }

    /**
     * Edit page header includes custom row actions.
     * EntityWithRowActions excludes 'edit' via AdminActionsConfig — custom actions still appear.
     */
    public function testEditPageHeaderIncludesCustomRowActions(): void
    {
        $entity = new EntityWithRowActions();
        $entity->setName('Archive Test Item');
        $entity->setStatus('pending');
        $this->em->persist($entity);
        $this->em->flush();

        $this->client->request('GET', '/admin/entity-with-row-actions/' . $entity->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();

        $this->assertStringContainsString('Approve', $content);
    }
}
