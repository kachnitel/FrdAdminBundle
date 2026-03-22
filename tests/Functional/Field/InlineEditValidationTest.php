<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional\Field;

use Kachnitel\AdminBundle\Tests\Fixtures\InlineEditValidatableEntity;
use Kachnitel\AdminBundle\Tests\Functional\ComponentTestCase;
use Kachnitel\EntityComponentsBundle\Components\Field\FloatField;
use Kachnitel\EntityComponentsBundle\Components\Field\StringField;

/**
 * Functional tests for the validation and save-feedback path in AbstractEditableField.save().
 *
 * @group inline-edit
 * @group inline-edit-validation
 * @group inline-edit-save
 */
class InlineEditValidationTest extends ComponentTestCase
{
    private function createEntity(string $title = 'Valid Title', float $score = 50.0): InlineEditValidatableEntity
    {
        $entity = new InlineEditValidatableEntity();
        $entity->setTitle($title);
        $entity->setScore($score);
        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    // ── Validation: StringField ───────────────────────────────────────────────

    public function testSaveWithTooLongTitleSetsErrorMessage(): void
    {
        $entity = $this->createEntity();

        $component = static::getContainer()->get(StringField::class);
        $component->editMode = true;
        $component->mount($entity, 'title');
        $component->currentValue = str_repeat('x', 25);

        $component->save();

        $this->assertNotSame('', $component->errorMessage, 'errorMessage must be set when validation fails');
        $this->assertTrue($component->editMode, 'editMode must stay true after failed validation');
    }

    public function testSaveWithBlankTitleSetsErrorMessage(): void
    {
        $entity = $this->createEntity();

        $component = static::getContainer()->get(StringField::class);
        $component->editMode = true;
        $component->mount($entity, 'title');
        $component->currentValue = '';

        $component->save();

        $this->assertNotSame('', $component->errorMessage);
    }

    public function testValidationFailureDoesNotPersistToDB(): void
    {
        $entity = $this->createEntity('Original');
        $id     = $entity->getId();

        $component = static::getContainer()->get(StringField::class);
        $component->editMode = true;
        $component->mount($entity, 'title');
        $component->currentValue = str_repeat('x', 25);

        $component->save();

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditValidatableEntity::class, $id);
        $this->assertSame('Original', $reloaded?->getTitle(), 'DB value must not change after a failed validation');
    }

    public function testValidSaveClearsErrorAndSetsSuccessFlag(): void
    {
        $entity = $this->createEntity('Old');

        $component = static::getContainer()->get(StringField::class);
        $component->editMode     = true;
        $component->errorMessage = 'Previous error';
        $component->mount($entity, 'title');
        $component->currentValue = 'New';

        $component->save();

        $this->assertSame('', $component->errorMessage);
        $this->assertTrue($component->saveSuccess);
        $this->assertFalse($component->editMode);
    }

    public function testValidSavePersistsToDB(): void
    {
        $entity = $this->createEntity('Old Title');
        $id     = $entity->getId();

        $component = static::getContainer()->get(StringField::class);
        $component->editMode = true;
        $component->mount($entity, 'title');
        $component->currentValue = 'New Title';

        $component->save();

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditValidatableEntity::class, $id);
        $this->assertSame('New Title', $reloaded?->getTitle());
    }

    // ── Validation: FloatField ─────────────────────────────────────────────────

    public function testSaveWithOutOfRangeScoreSetsErrorMessage(): void
    {
        $entity = $this->createEntity();

        $component = static::getContainer()->get(FloatField::class);
        $component->editMode = true;
        $component->mount($entity, 'score');
        $component->currentValue = 150.0;

        $component->save();

        $this->assertNotSame('', $component->errorMessage);
        $this->assertTrue($component->editMode);
    }

    // ── activateEditing clears feedback state ─────────────────────────────────

    public function testActivateEditingClearsFeedbackState(): void
    {
        $entity = $this->createEntity();

        $component = static::getContainer()->get(StringField::class);
        $component->mount($entity, 'title');
        $component->errorMessage = 'Stale error';
        $component->saveSuccess  = true;
        $component->editMode     = false;

        $component->activateEditing();

        $this->assertSame('', $component->errorMessage);
        $this->assertFalse($component->saveSuccess);
        $this->assertTrue($component->editMode);
    }

    // ── Template output ────────────────────────────────────────────────────────

    public function testTemplateRendersErrorMessage(): void
    {
        $entity = $this->createEntity();

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:String',
            data: [
                'entity'       => $entity,
                'property'     => 'title',
                'editMode'     => true,
                'errorMessage' => 'Title is too long.',
            ],
        );

        $html = (string) $component->render();

        $this->assertStringContainsString('Title is too long.', $html);
        $this->assertStringContainsString('is-invalid', $html);
    }

    public function testTemplateRendersSaveSuccessIndicator(): void
    {
        $entity = $this->createEntity();

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:String',
            data: [
                'entity'      => $entity,
                'property'    => 'title',
                'editMode'    => false,
                'saveSuccess' => true,
            ],
        );

        $html = (string) $component->render();

        $this->assertStringContainsString('inline-edit-saved', $html);
        $this->assertStringContainsString('✓', $html);
    }

    public function testTemplateDoesNotRenderSuccessIndicatorByDefault(): void
    {
        $entity = $this->createEntity();

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:String',
            data: [
                'entity'      => $entity,
                'property'    => 'title',
                'editMode'    => false,
                'saveSuccess' => false,
            ],
        );

        $html = (string) $component->render();

        $this->assertStringNotContainsString('inline-edit-saved', $html);
    }
}
