import { Controller } from '@hotwired/stimulus';

/**
 * Minimal Stimulus controller for multi-select with shift/ctrl support.
 *
 * Supports:
 * - Click to toggle individual selection
 * - Shift+Click for range selection between last clicked and current
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
     * Handle checkbox click with modifier key support.
     *
     * Must use 'click' event (not 'change') to properly detect shift key
     * and prevent default text selection behavior.
     *
     * @param {Event} event - Click event with potential shift/ctrl/meta modifiers
     */
    toggle(event) {
        const checkbox = event.currentTarget;
        const currentIndex = this.checkboxTargets.indexOf(checkbox);

        // Shift+Click: Range selection from last clicked to current
        if (event.shiftKey && this.lastCheckedIndexValue !== -1) {
            // Prevent default browser behavior (text selection)
            event.preventDefault();

            // Determine the desired state - we want to apply the state
            // that the clicked checkbox will have AFTER this click
            const checked = !checkbox.checked;
            
            // Apply to the clicked checkbox
            checkbox.checked = checked;
            
            // Apply to the range
            this.selectRange(this.lastCheckedIndexValue, currentIndex, checked);

            // Manually dispatch change for LiveComponent sync
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        }
        // Regular click or Ctrl/Cmd+Click: Individual toggle
        // (checkbox toggles naturally, change event fires automatically)

        // Remember this checkbox as the last clicked for next shift+click
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
