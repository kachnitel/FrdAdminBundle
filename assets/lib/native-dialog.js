/**
 * Shared helpers for Stimulus controllers driving a native <dialog>.
 *
 * The calling controller must declare a `dialog` target and a `dialogId`
 * string value (see admin-inline-add and admin-collection-dialog).
 */

/**
 * Resolve the dialog element — prefer the Stimulus target, fall back to
 * getElementById when a LiveComponent re-render replaced the element the
 * target was originally pointing to.
 *
 * @param {import('@hotwired/stimulus').Controller} controller
 * @param {string} name  Controller name, used in the error message
 * @returns {HTMLDialogElement}
 */
export function resolveDialog(controller, name) {
    if (controller.hasDialogTarget) {
        return /** @type {HTMLDialogElement} */ (controller.dialogTarget);
    }

    const el = document.getElementById(controller.dialogIdValue);
    if (!el) {
        throw new Error(
            `${name}: dialog element not found (id="${controller.dialogIdValue}")`
        );
    }

    return /** @type {HTMLDialogElement} */ (el);
}

/**
 * True when a click landed on the <dialog> element itself (its backdrop /
 * padding) rather than on content inside it. Bind on the <dialog> and keep
 * its own padding at 0, with an inner wrapper providing the visual padding.
 *
 * @param {Event} event
 * @returns {boolean}
 */
export function isBackdropClick(event) {
    return event.target === event.currentTarget;
}
