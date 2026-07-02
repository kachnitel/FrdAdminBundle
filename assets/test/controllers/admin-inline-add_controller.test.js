import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';
import { waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers.js';
import AdminInlineAddController from '../../controllers/admin-inline-add_controller.js';

const IDENTIFIER = 'admin-inline-add';
const SAVED_EVENT = 'admin:inline:entity:saved';

describe('admin-inline-add controller', () => {
    /** @type {Application} */
    let application;

    beforeEach(() => {
        application = Application.start();
        application.register(IDENTIFIER, AdminInlineAddController);

        // Stimulus's default logger is `console`. Its handleError() formats
        // action-invocation errors via console.error('%o', error, detail) —
        // `detail` carries the live DOM element, and Node's util.inspect() on
        // that element triggers a real jsdom CSSStyleSheet-binding bug
        // (unrelated to this controller). A silent logger avoids it and keeps
        // test output free of Stimulus's routine error/warning noise.
        application.logger = {
            error() {}, warn() {}, log() {}, groupCollapsed() {}, groupEnd() {},
        };
    });

    afterEach(async () => {
        // application.stop() does NOT call disconnect() on already-connected
        // controllers (confirmed empirically) — it only stops the MutationObserver
        // from processing future changes. Since this controller registers its own
        // window-level listener in connect(), an undisconnected instance keeps that
        // listener alive for the rest of the test file (jsdom shares one `window`
        // per file), so every later dispatchSaved() call would hit every previous
        // test's controller too.
        //
        // Fix: clear the DOM first, while the MutationObserver is still live, so
        // Stimulus's normal removal-triggers-disconnect() path actually runs (this
        // is the same mechanism the "disconnect lifecycle" test below verifies
        // directly), and wait for it to finish before stopping the application.
        clearDOM();
        await waitFor(() => {
            if (application.controllers.length > 0) {
                throw new Error('Controllers have not disconnected yet');
            }
        });
        application.stop();
    });

    /**
     * Mount a controller root with an open button, a close button, and a
     * <dialog>, and wait for Stimulus to connect.
     *
     * jsdom does not implement HTMLDialogElement.showModal()/close() — both
     * are `undefined` on the element, not just inert no-ops — so both are
     * replaced with vi.fn() stubs on the mounted dialog before any test
     * interacts with it. This is a genuine environment gap, not a controller
     * bug: real browsers implement both methods natively.
     */
    async function mountWidget({
        entityClass = 'App\\Entity\\Category',
        fieldName = 'order[category]',
        dialogId = 'category-dialog',
        withDialogTarget = true,
    } = {}) {
        const container = mountDOM(`
            <div data-controller="${IDENTIFIER}"
                 data-${IDENTIFIER}-entity-class-value="${entityClass}"
                 data-${IDENTIFIER}-field-name-value="${fieldName}"
                 data-${IDENTIFIER}-dialog-id-value="${dialogId}">
                <button type="button" data-action="${IDENTIFIER}#open">Add</button>
                <button type="button" data-action="${IDENTIFIER}#close">Cancel</button>
                <dialog id="${dialogId}"${withDialogTarget ? ` data-${IDENTIFIER}-target="dialog"` : ''}></dialog>
            </div>
        `);
        const root = container.firstElementChild;

        await waitFor(() => {
            if (!application.getControllerForElementAndIdentifier(root, IDENTIFIER)) {
                throw new Error('Controller has not connected yet');
            }
        });

        const dialog = root.querySelector('dialog');
        dialog.showModal = vi.fn();
        dialog.close = vi.fn();

        const buttons = root.querySelectorAll('button');

        return { root, dialog, openButton: buttons[0], closeButton: buttons[1] };
    }

    /**
     * Attach a plain <select> with a stub Tom Select instance, matching the
     * lookup #selectNewOption() performs via document.getElementsByName().
     *
     * @param {string} name
     * @param {{multiple?: boolean, existingOptionIds?: string[]}} [opts]
     */
    function mountSelectWithTomSelect(name, { multiple = false, existingOptionIds = [] } = {}) {
        const select = document.createElement('select');
        select.name = name;
        select.multiple = multiple;
        document.body.appendChild(select);

        const options = {};
        existingOptionIds.forEach((id) => { options[id] = { value: id }; });

        const ts = {
            options,
            addOption: vi.fn((opt) => { options[opt.value] = opt; }),
            addItem: vi.fn(),
            setValue: vi.fn(),
        };
        select.tomselect = ts;

        return { select, ts };
    }

    function dispatchSaved(detail) {
        window.dispatchEvent(new CustomEvent(SAVED_EVENT, { detail }));
    }

    // ── open() ──────────────────────────────────────────────────────────────

    it('open() calls showModal() on the dialog', async () => {
        const { dialog, openButton } = await mountWidget();

        openButton.click();

        expect(dialog.showModal).toHaveBeenCalledTimes(1);
    });

    it('open() prevents the click\u2019s default action', async () => {
        const { openButton } = await mountWidget();

        const event = new MouseEvent('click', { bubbles: true, cancelable: true });
        openButton.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
    });

    // ── close() ─────────────────────────────────────────────────────────────

    it('close() calls close() on the dialog', async () => {
        const { dialog, closeButton } = await mountWidget();

        closeButton.click();

        expect(dialog.close).toHaveBeenCalledTimes(1);
    });

    // ── #dialog() resolution ────────────────────────────────────────────────

    it('falls back to getElementById when no Stimulus target is registered', async () => {
        const { dialog, openButton } = await mountWidget({ withDialogTarget: false });

        openButton.click();

        expect(dialog.showModal).toHaveBeenCalledTimes(1);
    });

    it('surfaces a descriptive error when neither a target nor a matching id exists', async () => {
        const { openButton, dialog } = await mountWidget({
            withDialogTarget: false,
            dialogId: 'category-dialog',
        });
        // Detach the only element with a matching id — nothing left to fall back to.
        dialog.remove();

        // Stimulus's action dispatcher (Dispatcher#invokeWithEvent) catches
        // exceptions thrown by controller methods itself and reports them via
        // Application#handleError, which calls `window.onerror(...)` directly —
        // it never dispatches a native 'error' Event, so
        // window.addEventListener('error', ...) never fires for this path.
        let caught;
        const originalOnError = window.onerror;
        window.onerror = (message, _source, _line, _col, error) => { caught = error ?? message; };

        try {
            openButton.click();

            await waitFor(() => {
                if (!caught) throw new Error('expected an error to have surfaced');
            });
            expect(String(caught)).toContain('dialog element not found (id="category-dialog")');
        } finally {
            window.onerror = originalOnError;
        }
    });

    // ── admin:inline:entity:saved — entityClass guard ──────────────────────

    it('closes the dialog when the saved event\u2019s entityClass matches', async () => {
        const { dialog } = await mountWidget({ entityClass: 'App\\Entity\\Category' });

        dispatchSaved({ entityClass: 'App\\Entity\\Category', entityId: 42, entityLabel: 'Books' });

        expect(dialog.close).toHaveBeenCalledTimes(1);
    });

    it('ignores the saved event when entityClass does not match', async () => {
        const { dialog } = await mountWidget({ entityClass: 'App\\Entity\\Category' });

        dispatchSaved({ entityClass: 'App\\Entity\\Tag', entityId: 42, entityLabel: 'Fiction' });

        expect(dialog.close).not.toHaveBeenCalled();
    });

    it('only the matching instance reacts when two dialogs are mounted at once', async () => {
        const category = await mountWidget({
            entityClass: 'App\\Entity\\Category', dialogId: 'category-dialog', fieldName: 'order[category]',
        });
        const tag = await mountWidget({
            entityClass: 'App\\Entity\\Tag', dialogId: 'tag-dialog', fieldName: 'order[tags]',
        });

        dispatchSaved({ entityClass: 'App\\Entity\\Tag', entityId: 7, entityLabel: 'Sale' });

        expect(tag.dialog.close).toHaveBeenCalledTimes(1);
        expect(category.dialog.close).not.toHaveBeenCalled();
    });

    // ── admin:inline:entity:saved — Tom Select wiring ───────────────────────

    it('does not throw when no select element matches fieldName', async () => {
        const { dialog } = await mountWidget({ fieldName: 'order[category]' });

        expect(() => dispatchSaved({
            entityClass: 'App\\Entity\\Category', entityId: 42, entityLabel: 'Books',
        })).not.toThrow();
        expect(dialog.close).toHaveBeenCalledTimes(1);
    });

    it('does not throw when the matched select has no Tom Select instance attached', async () => {
        const { dialog } = await mountWidget({ fieldName: 'order[category]' });
        const plainSelect = document.createElement('select');
        plainSelect.name = 'order[category]';
        document.body.appendChild(plainSelect);

        expect(() => dispatchSaved({
            entityClass: 'App\\Entity\\Category', entityId: 42, entityLabel: 'Books',
        })).not.toThrow();
        expect(dialog.close).toHaveBeenCalledTimes(1);
    });

    it('falls back to the "[]" suffixed name for multi-valued selects', async () => {
        await mountWidget({ fieldName: 'order[tags]' });
        const { ts } = mountSelectWithTomSelect('order[tags][]', { multiple: true });

        dispatchSaved({ entityClass: 'App\\Entity\\Category', entityId: 9, entityLabel: 'Clearance' });

        expect(ts.addOption).toHaveBeenCalledWith({ value: '9', text: 'Clearance' });
    });

    it('adds a new option with the given label', async () => {
        await mountWidget({ fieldName: 'order[category]' });
        const { ts } = mountSelectWithTomSelect('order[category]');

        dispatchSaved({ entityClass: 'App\\Entity\\Category', entityId: 42, entityLabel: 'Books' });

        expect(ts.addOption).toHaveBeenCalledWith({ value: '42', text: 'Books' });
    });

    it('defaults the label to "#id" when entityLabel is missing', async () => {
        await mountWidget({ fieldName: 'order[category]' });
        const { ts } = mountSelectWithTomSelect('order[category]');

        dispatchSaved({ entityClass: 'App\\Entity\\Category', entityId: 42 });

        expect(ts.addOption).toHaveBeenCalledWith({ value: '42', text: '#42' });
    });

    it('coerces a numeric entityId to a string', async () => {
        await mountWidget({ fieldName: 'order[category]' });
        const { ts } = mountSelectWithTomSelect('order[category]');

        dispatchSaved({ entityClass: 'App\\Entity\\Category', entityId: 42, entityLabel: 'Books' });

        expect(ts.addOption.mock.calls[0][0].value).toBe('42'); // string, not number
    });

    it('does not add a duplicate option when entityId already exists, but still selects it', async () => {
        await mountWidget({ fieldName: 'order[category]' });
        const { ts } = mountSelectWithTomSelect('order[category]', { existingOptionIds: ['42'] });

        dispatchSaved({ entityClass: 'App\\Entity\\Category', entityId: 42, entityLabel: 'Books' });

        expect(ts.addOption).not.toHaveBeenCalled();
        expect(ts.setValue).toHaveBeenCalledWith('42');
    });

    it('single select calls setValue(), not addItem()', async () => {
        await mountWidget({ fieldName: 'order[category]' });
        const { ts } = mountSelectWithTomSelect('order[category]', { multiple: false });

        dispatchSaved({ entityClass: 'App\\Entity\\Category', entityId: 42, entityLabel: 'Books' });

        expect(ts.setValue).toHaveBeenCalledWith('42');
        expect(ts.addItem).not.toHaveBeenCalled();
    });

    it('multiple select calls addItem(), not setValue()', async () => {
        await mountWidget({ fieldName: 'order[tags]' });
        const { ts } = mountSelectWithTomSelect('order[tags]', { multiple: true });

        dispatchSaved({ entityClass: 'App\\Entity\\Category', entityId: 9, entityLabel: 'Clearance' });

        expect(ts.addItem).toHaveBeenCalledWith('9');
        expect(ts.setValue).not.toHaveBeenCalled();
    });

    // ── connect() / disconnect() lifecycle ──────────────────────────────────

    it('stops reacting to the saved event after the controller disconnects', async () => {
        const { root, dialog } = await mountWidget();

        root.remove();
        await waitFor(() => {
            if (application.getControllerForElementAndIdentifier(root, IDENTIFIER)) {
                throw new Error('Controller has not disconnected yet');
            }
        });

        dispatchSaved({ entityClass: 'App\\Entity\\Category', entityId: 42, entityLabel: 'Books' });

        expect(dialog.close).not.toHaveBeenCalled();
    });
});
