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
    static targets = ['checkbox'];
    static values = {
        lastCheckedIndex: Number
    };

    connect() {
        this.lastCheckedIndexValue = -1;
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
    }

    /**
     * Select/deselect a range of checkboxes.
     * Dispatches change events to trigger data-model binding.
     *
     * @param {number} start - Start index
     * @param {number} end - End index
     * @param {boolean} checked - Whether to check or uncheck
     */
    selectRange(start, end, checked) {
        const [min, max] = start < end ? [start, end] : [end, start];

        for (let i = min; i <= max; i++) {
            const checkbox = this.checkboxTargets[i];
            if (checkbox && checkbox.checked !== checked) {
                checkbox.checked = checked;
                // Dispatch change event to trigger data-model binding
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    }

    /**
     * Select all checkboxes on current page.
     * Dispatches change events to trigger data-model binding.
     */
    selectAll() {
        this.checkboxTargets.forEach(checkbox => {
            if (!checkbox.checked) {
                checkbox.checked = true;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    /**
     * Deselect all checkboxes.
     * Dispatches change events to trigger data-model binding.
     */
    deselectAll() {
        this.checkboxTargets.forEach(checkbox => {
            if (checkbox.checked) {
                checkbox.checked = false;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }
}
