<?php
// tests/Functional/SaveButtonIntegrationTest.php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntityFormType;
use Kachnitel\AdminBundle\Twig\Components\AdminEntityForm;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Functional coverage for the K:Admin:Action:Save / K:Admin:EntityForm
 * event-based integration: 'save' broadcast triggering AdminEntityForm::save(),
 * and 'admin:form:state' broadcasting the result back.
 *
 * Both components are mounted independently via InteractsWithLiveComponents,
 * not nested — exactly how they're actually rendered (header block vs.
 * content block; see admin/edit.html.twig). TestLiveComponent::emit()
 * simulates one component RECEIVING a broadcast (it looks up the matching
 * #[LiveListener] and calls it directly), which is the real mechanism these
 * two components use to talk to each other, just without an actual browser
 * DOM event bus in between. What this suite cannot cover: the real Stimulus
 * JS wiring that delivers a browser CustomEvent from one mounted component to
 * another. That's only verifiable with a true browser test (Panther), which
 * the project's own notes mark as currently blocked — flagging it here as a
 * known gap rather than silently pretending this is full E2E coverage.
 *
 * @group save-button
 */
#[Group('save-button')]
class SaveButtonIntegrationTest extends ComponentTestCase
{
    private const FORM_NAME = 'test_entity_form';

    protected function setUp(): void
    {
        parent::setUp();

        // CSRF token storage requires a session on the request stack.
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        self::getContainer()->get('request_stack')->push($request);
    }

    // ── SaveButton on its own ────────────────────────────────────────────────

    public function testTriggerSaveSetsSavingAndEmitsSaveEvent(): void
    {
        $button = $this->saveButtonComponent();

        $button->call('triggerSave');

        $this->assertTrue($button->component()->saving);
        $this->assertArrayHasKey('save', $this->emittedEvents($button));
    }

    public function testReceivingValidFormStateClearsSavingAndMarksValid(): void
    {
        $button = $this->saveButtonComponent();
        $button->call('triggerSave');

        $button->emit('admin:form:state', ['valid' => 1]);

        $this->assertFalse($button->component()->saving);
        $this->assertTrue($button->component()->valid);
    }

    public function testReceivingInvalidFormStateClearsSavingButMarksInvalid(): void
    {
        $button = $this->saveButtonComponent();
        $button->call('triggerSave');

        $button->emit('admin:form:state', ['valid' => 0]);

        $this->assertFalse($button->component()->saving);
        $this->assertFalse($button->component()->valid);
    }

    public function testInvalidStateDoesNotRenderDisabledAttribute(): void
    {
        // Regression guard: $valid must never gate `disabled`, only $saving does.
        // Gating on $valid would brick the button — nothing besides another
        // save attempt could ever flip it back, and a disabled button can't
        // be clicked to make that attempt. See SaveButton's class docblock.
        $button = $this->saveButtonComponent();
        $button->call('triggerSave');
        $button->emit('admin:form:state', ['valid' => 0]);

        $rendered = (string) $button->render();

        $this->assertStringNotContainsString('disabled', $rendered);
        $this->assertStringContainsString('aria-invalid="true"', $rendered);
    }

    // ── AdminEntityForm's side of the broadcast ─────────────────────────────

    public function testFailedSaveBroadcastsInvalidState(): void
    {
        $entity = $this->createEntity('Keep Me');
        $form = $this->mountEditForm($entity);

        $form->set(self::FORM_NAME, ['name' => '']);
        $form->emit('save');

        $this->assertSame(['valid' => 0], $this->emittedEvents($form)['admin:form:state'] ?? null);

        $this->em->clear();
        $reloaded = $this->em->find(TestEntity::class, $entity->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame('Keep Me', $reloaded->getName());
    }

    public function testSuccessfulSaveBroadcastsValidState(): void
    {
        $entity = $this->createEntity('Before');
        $form = $this->mountEditForm($entity);

        $form->set(self::FORM_NAME, ['name' => 'After']);
        $form->emit('save');

        $this->assertSame(['valid' => 1], $this->emittedEvents($form)['admin:form:state'] ?? null);

        $this->em->clear();
        $reloaded = $this->em->find(TestEntity::class, $entity->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame('After', $reloaded->getName());
    }

    public function testSaveListenerNameMatchesSaveButtonsBroadcast(): void
    {
        // Pins the exact event-name contract: a mismatch here (e.g. someone
        // renames 'save' on one side only) breaks the real button silently,
        // since emit() with no matching listener just does nothing — it
        // doesn't throw on the production code path. TestLiveComponent::emit()
        // DOES throw if there's no matching #[LiveListener], so simply
        // reaching the assertions below already proves the names match.
        $entity = $this->createEntity('Untouched');
        $form = $this->mountEditForm($entity);

        $form->set(self::FORM_NAME, ['name' => 'Touched']);
        $form->emit('save');

        $this->em->clear();
        $reloaded = $this->em->find(TestEntity::class, $entity->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame('Touched', $reloaded->getName());
    }

    // ── Full round trip, both components ────────────────────────────────────

    public function testFullCycleInvalidThenFixedReflectsCorrectlyOnSaveButton(): void
    {
        $entity = $this->createEntity('Keep Me');
        $form = $this->mountEditForm($entity);
        $button = $this->saveButtonComponent();

        // 1. Click save with a blank (invalid) name.
        $button->call('triggerSave');
        $this->assertTrue($button->component()->saving);

        $form->set(self::FORM_NAME, ['name' => '']);
        $form->emit('save');
        $invalidPayload = $this->emittedEvents($form)['admin:form:state'] ?? null;
        $this->assertSame(['valid' => 0], $invalidPayload);

        $button->emit('admin:form:state', $invalidPayload);
        $this->assertFalse($button->component()->saving);
        $this->assertFalse($button->component()->valid);

        // 2. Fix the field and save again.
        $button->call('triggerSave');
        $form->set(self::FORM_NAME, ['name' => 'Fixed']);
        $form->emit('save');
        $validPayload = $this->emittedEvents($form)['admin:form:state'] ?? null;
        $this->assertSame(['valid' => 1], $validPayload);

        $button->emit('admin:form:state', $validPayload);
        $this->assertFalse($button->component()->saving);
        $this->assertTrue($button->component()->valid);

        $this->em->clear();
        $reloaded = $this->em->find(TestEntity::class, $entity->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame('Fixed', $reloaded->getName());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createEntity(string $name): TestEntity
    {
        $entity = new TestEntity();
        $entity->setName($name);
        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    private function mountEditForm(TestEntity $entity): TestLiveComponent
    {
        return $this->createLiveComponent(
            name: AdminEntityForm::class,
            data: [
                'entityClass'   => TestEntity::class,
                'entityId'      => $entity->getId(),
                'formTypeClass' => TestEntityFormType::class,
            ],
        );
    }

    private function saveButtonComponent(): TestLiveComponent
    {
        return $this->createLiveComponent(name: 'K:Admin:Action:Save');
    }

    /**
     * Reads a component's emitted broadcast events from its last render,
     * keyed by event name, each value the raw 'data' payload array.
     *
     * symfony/ux-live-component renamed the HTML attribute carrying this data
     * partway through the 2.x series (data-live-emit ->
     * data-live-events-to-emit-value); composer.json only pins ^2.13, so
     * either could be the locked version. Reading both keeps this correct
     * regardless of which minor version is actually installed.
     *
     * @return array<string, array<string, mixed>>
     */
    private function emittedEvents(TestLiveComponent $component): array
    {
        $node = $component->render()->crawler()->filter('[data-live-name-value]');
        $raw = $node->attr('data-live-events-to-emit-value') ?? $node->attr('data-live-emit');

        if ($raw === null) {
            return [];
        }

        $byName = [];
        foreach (json_decode($raw, true, flags: JSON_THROW_ON_ERROR) as $event) {
            $byName[$event['event']] = $event['data'];
        }

        return $byName;
    }
}
