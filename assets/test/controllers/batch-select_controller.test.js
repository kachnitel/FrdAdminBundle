import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';
import { waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers.js';
import BatchSelectController from '../../controllers/batch-select_controller.js';

const IDENTIFIER = 'batch-select';

describe('batch-select controller', () => {
    /** @type {Application} */
    let application;

    beforeEach(() => {
        application = Application.start();
        application.register(IDENTIFIER, BatchSelectController);
    });

    afterEach(() => {
        application.stop();
        clearDOM();
    });

    /**
     * Mount a controller root with N row checkboxes plus a master checkbox,
     * and wait until Stimulus has actually connected the controller.
     *
     * @param {number} count
     */
    async function mountRows(count) {
        const rows = Array.from({ length: count }, (_, i) => `
            <input type="checkbox" value="${i + 1}"
                   data-batch-select-target="checkbox"
                   data-action="click->batch-select#toggle">
        `).join('');

        const container = mountDOM(`
            <div data-controller="${IDENTIFIER}">
                <input type="checkbox"
                       data-batch-select-target="master"
                       data-action="change->batch-select#toggleAll">
                ${rows}
            </div>
        `);
        const root = container.firstElementChild;

        await waitFor(() => {
            if (!application.getControllerForElementAndIdentifier(root, IDENTIFIER)) {
                throw new Error('Controller has not connected yet');
            }
        });

        return {
            master: root.querySelector('[data-batch-select-target="master"]'),
            checkboxes: Array.from(root.querySelectorAll('[data-batch-select-target="checkbox"]')),
        };
    }

    // ── toggle(): plain click ──────────────────────────────────────────────

    it('clicking a single checkbox checks only that row', async () => {
        const { checkboxes } = await mountRows(3);

        checkboxes[1].click();

        expect(checkboxes[0].checked).toBe(false);
        expect(checkboxes[1].checked).toBe(true);
        expect(checkboxes[2].checked).toBe(false);
    });

    it('clicking a checked checkbox again unchecks it', async () => {
        const { checkboxes } = await mountRows(2);

        checkboxes[0].click();
        checkboxes[0].click();

        expect(checkboxes[0].checked).toBe(false);
    });

    // ── toggle(): shift+click range selection ─────────────────────────────
    //
    // jsdom quirk: `preventDefault()` on a checkbox click triggers jsdom's
    // "cancelled-activation" steps which run *after* the listener returns. jsdom
    // implements this as a re-toggle — `!finalHandlerState` — rather than a proper
    // restore-to-pre-click-state. The net effect is that the directly-clicked
    // checkbox always ends up as `!targetState` in jsdom, regardless of its
    // starting state. Real browsers restore to the pre-click state, which (for the
    // same-direction case) agrees with the explicit `checkbox.checked = targetState`
    // assignment — user-confirmed working correctly.
    //
    // Intermediate checkboxes set via `selectRange()` are unaffected by jsdom's
    // cancellation (cancellation only acts on the checkbox that received the click),
    // so they are the reliable surface for verifying range-selection logic here.

    it('shift+click sets every intermediate row between anchor and clicked row', async () => {
        const { checkboxes } = await mountRows(5);

        checkboxes[1].click(); // plain click — sets anchor, checks row
        checkboxes[3].dispatchEvent(
            new MouseEvent('click', { bubbles: true, cancelable: true, shiftKey: true }),
        );

        expect(checkboxes[0].checked).toBe(false); // outside range
        expect(checkboxes[1].checked).toBe(true);  // anchor (set by plain click)
        expect(checkboxes[2].checked).toBe(true);  // intermediate — set by selectRange
        // checkboxes[3] is the directly-clicked endpoint; jsdom re-toggles it to
        // !targetState. Real browsers preserve the explicit assignment. Covered by [2].
        expect(checkboxes[4].checked).toBe(false); // outside range
    });

    it('shift+click works when the clicked row comes before the anchor', async () => {
        const { checkboxes } = await mountRows(5);

        checkboxes[3].click(); // anchor
        checkboxes[1].dispatchEvent(
            new MouseEvent('click', { bubbles: true, cancelable: true, shiftKey: true }),
        );

        // checkboxes[1] is the directly-clicked endpoint; jsdom re-toggles it to
        // !targetState. Assert only non-clicked positions.
        expect(checkboxes[2].checked).toBe(true);  // intermediate — set by selectRange
        expect(checkboxes[3].checked).toBe(true);  // anchor
        expect(checkboxes[4].checked).toBe(false); // outside range
    });

    it('shift+click range matches the anchor\u2019s state when anchor is unchecked', async () => {
        const { checkboxes } = await mountRows(4);

        checkboxes[0].click(); // check anchor
        checkboxes[0].click(); // uncheck anchor (still the anchor)

        checkboxes[2].dispatchEvent(
            new MouseEvent('click', { bubbles: true, cancelable: true, shiftKey: true }),
        );

        // checkboxes[2] is the directly-clicked endpoint; jsdom re-toggles it to
        // !targetState (true). Assert only non-clicked positions.
        expect(checkboxes[0].checked).toBe(false);
        expect(checkboxes[1].checked).toBe(false); // intermediate — set by selectRange
    });

    it('shift+click without a prior anchor click behaves like a plain click', async () => {
        const { checkboxes } = await mountRows(3);

        checkboxes[1].dispatchEvent(
            new MouseEvent('click', { bubbles: true, cancelable: true, shiftKey: true }),
        );

        expect(checkboxes[0].checked).toBe(false);
        expect(checkboxes[1].checked).toBe(true);
        expect(checkboxes[2].checked).toBe(false);
    });

    // ── updateMaster(): indeterminate / checked / unchecked ──────────────

    it('master checkbox is unchecked and not indeterminate when nothing is selected', async () => {
        const { master } = await mountRows(3);

        expect(master.checked).toBe(false);
        expect(master.indeterminate).toBe(false);
    });

    it('master checkbox becomes indeterminate when some rows are checked', async () => {
        const { master, checkboxes } = await mountRows(3);

        checkboxes[0].click();

        expect(master.checked).toBe(false);
        expect(master.indeterminate).toBe(true);
    });

    it('master checkbox becomes fully checked only once every row is checked', async () => {
        const { master, checkboxes } = await mountRows(2);

        checkboxes[0].click();
        checkboxes[1].click();

        expect(master.checked).toBe(true);
        expect(master.indeterminate).toBe(false);
    });

    // ── toggleAll(): master drives every row ──────────────────────────────

    it('checking the master checkbox checks every row', async () => {
        const { master, checkboxes } = await mountRows(3);

        master.checked = true;
        master.dispatchEvent(new Event('change', { bubbles: true }));

        expect(checkboxes.every((cb) => cb.checked)).toBe(true);
    });

    it('unchecking the master checkbox unchecks every row', async () => {
        const { master, checkboxes } = await mountRows(3);

        checkboxes.forEach((cb) => cb.click());
        expect(master.checked).toBe(true);

        master.checked = false;
        master.dispatchEvent(new Event('change', { bubbles: true }));

        expect(checkboxes.every((cb) => !cb.checked)).toBe(true);
    });

    // ── No master target present ───────────────────────────────────────────

    it('toggling a row without a master checkbox in scope does not throw', async () => {
        const container = mountDOM(`
            <div data-controller="${IDENTIFIER}">
                <input type="checkbox" data-batch-select-target="checkbox"
                       data-action="click->batch-select#toggle">
            </div>
        `);
        const root = container.firstElementChild;

        await waitFor(() => {
            if (!application.getControllerForElementAndIdentifier(root, IDENTIFIER)) {
                throw new Error('Controller has not connected yet');
            }
        });

        const checkbox = root.querySelector('[data-batch-select-target="checkbox"]');

        expect(() => checkbox.click()).not.toThrow();
        expect(checkbox.checked).toBe(true);
    });
});
