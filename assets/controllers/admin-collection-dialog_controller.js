/**
 * admin-collection-dialog Stimulus controller
 *
 * Opens a native <dialog> that embeds a scoped K:Admin:EntityListEmbedded for a
 * collection column (#[AdminColumn(collectionDialog: true)]). The embedded list is
 * mounted with loading="lazy", so nothing is fetched until the dialog is shown.
 *
 * Values:
 *   dialogId  {string}  id of the <dialog> element (fallback when the target is
 *                       lost after a LiveComponent re-render)
 *
 * Targets:
 *   dialog  — the <dialog> element inside this controller's root
 */
import { Controller } from '@hotwired/stimulus';
import { isBackdropClick, resolveDialog } from '../lib/native-dialog.js';

export default class extends Controller {
    static targets = ['dialog'];

    static values = {
        dialogId: String,
    };

    /**
     * Open the dialog. Bound via `data-action="admin-collection-dialog#open"`.
     *
     * @param {Event} event
     */
    open(event) {
        event.preventDefault();
        resolveDialog(this, 'admin-collection-dialog').showModal();
    }

    /**
     * Close the dialog. Bound via `data-action="admin-collection-dialog#close"`.
     */
    close() {
        resolveDialog(this, 'admin-collection-dialog').close();
    }

    /**
     * Close when the backdrop (the <dialog> element itself) is clicked.
     * Bound via `data-action="click->admin-collection-dialog#backdrop"`.
     *
     * @param {Event} event
     */
    backdrop(event) {
        if (isBackdropClick(event)) {
            this.close();
        }
    }
}
