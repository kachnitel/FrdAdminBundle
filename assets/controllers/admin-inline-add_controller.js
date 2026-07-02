/**
 * admin-inline-add Stimulus controller
 *
 * Manages the lifecycle of the EntityTypeAddButton inline-creation dialog:
 *   1. Opens the native <dialog> element on button click (showModal)
 *   2. Listens for the 'admin:inline:entity:saved' browser event dispatched by
 *      K:Admin:EntityType:InlineForm after a successful save
 *   3. Closes the dialog
 *   4. Auto-selects the newly created entity in the parent Tom Select widget
 *      (initialised by symfony/ux-autocomplete on the parent form's <select>)
 *
 * Values:
 *   entityClass  {string}  FQCN of the entity managed by this dialog
 *                          (used to ignore events from other inline-add dialogs
 *                           on the same page)
 *   fieldName    {string}  HTML name attribute of the parent <select> element
 *                          (e.g. "order[category]" or "order[tags]")
 *   dialogId     {string}  id of the <dialog> element (fallback when target is lost
 *                          after a LiveComponent re-render inside the dialog)
 *
 * Targets:
 *   dialog  — the <dialog> element inside this component's root
 */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog'];

    static values = {
        entityClass: String,
        fieldName: String,
        dialogId: String,
    };

    connect() {
        // Arrow-function wrapper avoids private-method binding edge cases and
        // keeps `this` correctly scoped for removeEventListener.
        this._onEntitySaved = (event) => this.#onEntitySaved(event);
        window.addEventListener('admin:inline:entity:saved', this._onEntitySaved);
    }

    disconnect() {
        window.removeEventListener('admin:inline:entity:saved', this._onEntitySaved);
        delete this._onEntitySaved;
    }

    /**
     * Open the dialog. Bound via `data-action="admin-inline-add#open"`.
     *
     * @param {Event} event
     */
    open(event) {
        event.preventDefault();
        this.#dialog().showModal();
    }

    /**
     * Close the dialog. Bound via `data-action="admin-inline-add#close"`.
     */
    close() {
        this.#dialog().close();
    }

    // ── Private ──────────────────────────────────────────────────────────────

    /**
     * Handle 'admin:inline:entity:saved' fired by InlineEntityForm after flush.
     *
     * @param {CustomEvent} event
     */
    #onEntitySaved(event) {
        const { entityClass, entityId, entityLabel } = event.detail ?? {};

        // Guard: ignore events belonging to other inline-add dialogs on the page.
        if (entityClass !== this.entityClassValue) {
            return;
        }

        this.close();
        this.#selectNewOption(String(entityId), entityLabel ?? `#${entityId}`);
    }

    /**
     * Add the new entity as a Tom Select option and select it.
     *
     * symfony/ux-autocomplete attaches Tom Select to the <select> element as
     * `element.tomselect`. We locate the select by its HTML `name` attribute,
     * falling back to `name[]` for multi-valued (multiple) selects since Symfony
     * appends `[]` to the rendered name but not to form.vars.full_name.
     *
     * @param {string} entityId
     * @param {string} entityLabel
     */
    #selectNewOption(entityId, entityLabel) {
        const fieldName = this.fieldNameValue;
        if (!fieldName) return;

        // getElementsByName handles brackets (e.g. "order[category]") without
        // CSS escaping — unlike querySelector('[name="..."]').
        const selectEl =
            document.getElementsByName(fieldName)[0] ??
            document.getElementsByName(fieldName + '[]')[0];

        if (!selectEl) return;

        // Tom Select instance is attached by symfony/ux-autocomplete.
        const ts = /** @type {any} */ (selectEl).tomselect;
        if (!ts) return;

        // Add the option if it does not already exist in Tom Select's option store.
        // Keys in ts.options are always strings.
        if (!ts.options[entityId]) {
            ts.addOption({ value: entityId, text: entityLabel });
        }

        // Single select: replace value. Multiple select: append to existing selection.
        if (selectEl.multiple) {
            ts.addItem(entityId);
        } else {
            ts.setValue(entityId);
        }
    }

    /**
     * Resolve the dialog element — prefer the Stimulus target, fall back to
     * getElementById when the LiveComponent re-render inside the dialog replaces
     * the element that the target was originally pointing to.
     *
     * @returns {HTMLDialogElement}
     */
    #dialog() {
        if (this.hasDialogTarget) {
            return /** @type {HTMLDialogElement} */ (this.dialogTarget);
        }

        const el = document.getElementById(this.dialogIdValue);
        if (!el) {
            throw new Error(
                `admin-inline-add: dialog element not found (id="${this.dialogIdValue}")`
            );
        }

        return /** @type {HTMLDialogElement} */ (el);
    }
}
