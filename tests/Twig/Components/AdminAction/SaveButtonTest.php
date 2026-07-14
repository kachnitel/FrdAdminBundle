<?php
// tests/Twig/Components/AdminAction/SaveButtonTest.php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Components\AdminAction;

use Kachnitel\AdminBundle\Twig\Components\AdminAction\SaveButton;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\UX\LiveComponent\LiveResponder;

/**
 * Unit-level coverage for SaveButton's own state transitions.
 *
 */
#[CoversClass(SaveButton::class)]
#[Group('save-button')]
final class SaveButtonTest extends TestCase
{
    public function testDefaultStateIsEnabledAndNotSaving(): void
    {
        $button = $this->createSaveButton();

        $this->assertFalse($button->saving);
        $this->assertTrue($button->valid);
    }

    public function testTriggerSaveSetsSavingState(): void
    {
        $button = $this->createSaveButton();

        $button->triggerSave();

        $this->assertTrue($button->saving);
    }

    /**
     * @param int<0, 1> $broadcastValid
     */
    #[DataProvider('formStateProvider')]
    public function testOnFormStateChangedTracksValidity(int $broadcastValid, bool $expectedValid): void
    {
        $button = $this->createSaveButton();

        $button->onFormStateChanged(valid: $broadcastValid);

        $this->assertSame($expectedValid, $button->valid);
    }

    /**
     * @return \Iterator<string, array{int, bool}>
     */
    public static function formStateProvider(): \Iterator
    {
        yield 'valid' => [1, true];
        yield 'invalid' => [0, false];
    }

    public function testOnFormStateChangedClearsSavingFlag(): void
    {
        $button = $this->createSaveButton();
        $button->triggerSave();
        $this->assertTrue($button->saving);

        $button->onFormStateChanged(valid: 1);

        $this->assertFalse($button->saving);
        $this->assertTrue($button->valid);
    }

    private function createSaveButton(): SaveButton
    {
        $button = new SaveButton();

        /**
         * ComponentToolsTrait::emit() reads a $liveResponder property that's normally
         * set via the #[Required] setLiveResponder() setter when the LiveComponent
         * framework instantiates the component through its service container. A bare
         * `new SaveButton()` skips that wiring, so any path through emit() (i.e.
         * triggerSave()) needs a LiveResponder injected manually first.
         */
        $button->setLiveResponder(new LiveResponder());

        return $button;
    }
}
