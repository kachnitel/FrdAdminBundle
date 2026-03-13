<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Kachnitel\AdminBundle\Tests\Fixtures\BasicAdminEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithRowActions;
use Kachnitel\AdminBundle\Tests\Fixtures\InlineEditEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests that show and edit page headers render the correct entity actions for their context.
 *
 * Context rules:
 *   - 'index' (entity list) — all actions including CONTEXT_INDEX-only (e.g. InlineEditButton)
 *   - 'show'               — all actions except CONTEXT_INDEX-only; 'show' action excluded (current page)
 *   - 'edit'               — all actions except CONTEXT_INDEX-only; 'edit' action excluded (current page)
 *
 * Icon reference:
 *   - Show action link:       👀  (DefaultRowActionProvider, contexts: [] → all)
 *   - Edit action link:       🖊  (DefaultRowActionProvider, contexts: [] → all)
 *   - Inline edit component:  ✏️  (InlineEditRowActionProvider, contexts: ['index'] → list only)
 *
 * Note on entity fixtures:
 *   - BasicAdminEntity — no enableInlineEdit, no explicit permissions → safe for edit visibility checks
 *   - InlineEditEntity — enableInlineEdit: true, no explicit permissions → used to verify context
 *     filtering: on show/edit pages, InlineEdit (contexts:['index']) is filtered out and Default
 *     edit link (🖊) appears instead
 *   - TestEntity — enableInlineEdit: true WITH permissions: ['edit' => 'ROLE_TEST_EDIT'] → edit
 *     action is correctly hidden for anonymous users (no login in NoAuthTestKernel); not used for
 *     edit visibility assertions
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

        $this->client = static::createClient();
        $this->em     = $this->client->getContainer()->get('doctrine')->getManager();

        $schemaTool = new SchemaTool($this->em);
        $metadata   = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    // -------------------------------------------------------------------------
    // Show page — BasicAdminEntity (no inline edit, no explicit permissions)
    // -------------------------------------------------------------------------

    /**
     * Show page header includes the plain Edit link (🖊) from DefaultRowActionProvider.
     */
    public function testShowPageHeaderIncludesEditAction(): void
    {
        $entity = new BasicAdminEntity();
        $entity->setName('Header Test');
        $this->em->persist($entity);
        $this->em->flush();

        $this->client->request('GET', '/admin/basic-admin-entity/' . $entity->getId());

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('🖊', (string) $this->client->getResponse()->getContent());
    }

    /**
     * Show page header does NOT include the Show action — it is the current page.
     */
    public function testShowPageHeaderExcludesShowAction(): void
    {
        $entity = new BasicAdminEntity();
        $entity->setName('No Show Button');
        $this->em->persist($entity);
        $this->em->flush();

        $this->client->request('GET', '/admin/basic-admin-entity/' . $entity->getId());

        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('👀', (string) $this->client->getResponse()->getContent());
    }

    // -------------------------------------------------------------------------
    // Show page — InlineEditEntity (enableInlineEdit: true, no explicit permissions)
    // -------------------------------------------------------------------------

    /**
     * For an inline-edit entity with no explicit permissions, the show page header must show
     * the plain-link Edit (🖊) and NOT the InlineEditButton (✏️).
     *
     * This verifies that RowActionRegistry context filtering works:
     *   - InlineEditRowActionProvider registers edit with contexts: ['index']
     *   - For context='show', that action is filtered BEFORE merging, leaving Default's plain link
     *   - Default's edit passes the voter (required_role: null → grant) and hasForm check
     */
    public function testShowPageHeaderShowsLinkEditNotInlineEditForInlineEditEntity(): void
    {
        $entity = new InlineEditEntity();
        $entity->setTitle('Inline Entity Show Test');
        $this->em->persist($entity);
        $this->em->flush();

        $this->client->request('GET', '/admin/inline-edit-entity/' . $entity->getId());

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();

        $this->assertStringContainsString('🖊', $content);
        $this->assertStringNotContainsString('✏️', $content);
    }

    // -------------------------------------------------------------------------
    // Edit page
    // -------------------------------------------------------------------------

    /**
     * Edit page header includes the Show action (👀).
     */
    public function testEditPageHeaderIncludesShowAction(): void
    {
        $entity = new BasicAdminEntity();
        $entity->setName('Edit Header Test');
        $this->em->persist($entity);
        $this->em->flush();

        $this->client->request('GET', '/admin/basic-admin-entity/' . $entity->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('👀', (string) $this->client->getResponse()->getContent());
    }

    /**
     * Edit page header excludes all edit actions — neither link (🖊) nor InlineEditButton (✏️).
     * Uses InlineEditEntity so InlineEditRowActionProvider applies; both variants must be absent.
     */
    public function testEditPageHeaderExcludesEditAction(): void
    {
        $entity = new InlineEditEntity();
        $entity->setTitle('No Edit Button');
        $this->em->persist($entity);
        $this->em->flush();

        $this->client->request('GET', '/admin/inline-edit-entity/' . $entity->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();

        $this->assertStringNotContainsString('🖊', $content);
        $this->assertStringNotContainsString('✏️', $content);
    }

    // -------------------------------------------------------------------------
    // Custom row actions
    // -------------------------------------------------------------------------

    /**
     * Custom actions (contexts: [] by default → all contexts) appear on the show page.
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
        $this->assertStringContainsString('Approve', (string) $this->client->getResponse()->getContent());
    }

    /**
     * Custom actions appear on the edit page header.
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
        $this->assertStringContainsString('Approve', (string) $this->client->getResponse()->getContent());
    }
}