import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Controller from '../../controllers/admin-collection-dialog_controller.js';

// jsdom does not implement HTMLDialogElement.showModal()/close(), so both are stubbed.
describe('admin-collection-dialog controller', () => {
    let application;
    let dialog;

    beforeEach(async () => {
        document.body.innerHTML = `
            <div data-controller="admin-collection-dialog"
                 data-admin-collection-dialog-dialog-id-value="d1">
                <button id="open" data-action="admin-collection-dialog#open">Open</button>
                <dialog id="d1"
                        data-admin-collection-dialog-target="dialog"
                        data-action="click->admin-collection-dialog#backdrop">
                    <div id="inner">
                        <button id="x" data-action="admin-collection-dialog#close">x</button>
                    </div>
                </dialog>
            </div>`;

        dialog = document.getElementById('d1');
        dialog.showModal = vi.fn();
        dialog.close = vi.fn();

        application = new Application();
        // Stimulus's default logger trips a jsdom CSSStyleSheet binding bug.
        const noop = () => {};
        application.logger = { debug: noop, log: noop, warn: noop, error: noop };
        application.register('admin-collection-dialog', Controller);
        await application.start();
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('opens the dialog modally', () => {
        document.getElementById('open').click();

        expect(dialog.showModal).toHaveBeenCalledOnce();
    });

    it('closes via the close button', () => {
        document.getElementById('x').click();

        expect(dialog.close).toHaveBeenCalledOnce();
    });

    it('closes on backdrop click but not on content click', () => {
        document.getElementById('inner').click();
        expect(dialog.close).not.toHaveBeenCalled();

        dialog.click();
        expect(dialog.close).toHaveBeenCalledOnce();
    });

    it('falls back to the dialogId value when the target is missing', () => {
        dialog.removeAttribute('data-admin-collection-dialog-target');

        document.getElementById('open').click();

        expect(dialog.showModal).toHaveBeenCalledOnce();
    });
});
