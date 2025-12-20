import { Controller } from '@hotwired/stimulus';

/**
 * Minimal Stimulus controller for multi-select with shift/ctrl support.
 *
 * Supports:
 * - Click to toggle individual selection
 * - Shift+Click for range selection
 * - Ctrl/Cmd+Click for multi-toggle
 */
export default class extends Controller {
    static targets = ['checkbox', 'selectedIds', 'count'];
    static values = {
        lastCheckedIndex: Number
    };

    connect() {
        this.lastCheckedIndexValue = -1;
        this.updateCount();
    }

    /**
     * Handle checkbox change with modifier key support.
     *
     * @param {Event} event - Click event with potential shift/ctrl/meta modifiers
     */
    toggle(event) {
        const checkbox = event.currentTarget;
        const currentIndex = this.checkboxTargets.indexOf(checkbox);

        // Shift+Click: Range selection
        if (event.shiftKey && this.lastCheckedIndexValue !== -1) {
            this.selectRange(this.lastCheckedIndexValue, currentIndex, checkbox.checked);
        }
        // Ctrl/Cmd+Click or regular click: Individual toggle
        // (No special handling needed - checkbox toggles naturally)

        this.lastCheckedIndexValue = currentIndex;
        this.updateSelectedIds();
    }

    /**
     * Select/deselect a range of checkboxes.
     *
     * @param {number} start - Start index
     * @param {number} end - End index
     * @param {boolean} checked - Whether to check or uncheck
     */
    selectRange(start, end, checked) {
        const [min, max] = start < end ? [start, end] : [end, start];

        for (let i = min; i <= max; i++) {
            if (this.checkboxTargets[i]) {
                this.checkboxTargets[i].checked = checked;
            }
        }
    }

    /**
     * Select all checkboxes on current page.
     */
    selectAll() {
        this.checkboxTargets.forEach(checkbox => {
            checkbox.checked = true;
        });
        this.updateSelectedIds();
    }

    /**
     * Deselect all checkboxes.
     */
    deselectAll() {
        this.checkboxTargets.forEach(checkbox => {
            checkbox.checked = false;
        });
        this.updateSelectedIds();
    }

    /**
     * Update the hidden selectedIds input with current selection.
     * This syncs with the LiveComponent's selectedIds LiveProp.
     */
    updateSelectedIds() {
        const selectedIds = this.checkboxTargets
            .filter(checkbox => checkbox.checked)
            .map(checkbox => parseInt(checkbox.value));

        // Update LiveComponent's selectedIds prop
        if (this.hasSelectedIdsTarget) {
            this.selectedIdsTarget.value = JSON.stringify(selectedIds);
            // Trigger change event to update LiveComponent
            this.selectedIdsTarget.dispatchEvent(new Event('change', { bubbles: true }));
        }

        this.updateCount();
    }

    /**
     * Update the selection count display.
     */
    updateCount() {
        if (this.hasCountTarget) {
            const count = this.checkboxTargets.filter(checkbox => checkbox.checked).length;
            this.countTarget.textContent = count;
        }
    }
}
