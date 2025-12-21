import { Controller } from '@hotwired/stimulus';

/**
 * Minimal Stimulus controller for multi-select with shift/ctrl support.
 *
 * Supports:
 * - Click to toggle individual selection
 * - Shift+Click for range selection between last clicked and current
 * - Ctrl/Cmd+Click for multi-toggle
 * - Master checkbox to select/deselect all
 */
export default class extends Controller {
    static targets = ['checkbox', 'master'];

    connect() {
        this.lastCheckedIndex = -1;
    }

    /**
     * Handle checkbox click with modifier key support.
     *
     * @param {Event} event - Click event with potential shift/ctrl/meta modifiers
     */
    toggle(event) {
        const checkbox = event.currentTarget;
        const currentIndex = this.checkboxTargets.indexOf(checkbox);

        // Shift+Click: Range selection from last clicked to current
        if (event.shiftKey && this.lastCheckedIndex !== -1) {
            event.preventDefault();
            
            // Look at the anchor checkbox's CURRENT state and apply to range
            const anchorCheckbox = this.checkboxTargets[this.lastCheckedIndex];
            const targetState = anchorCheckbox ? anchorCheckbox.checked : true;
            
            // Apply to all checkboxes in range (including endpoints)
            this.selectRange(this.lastCheckedIndex, currentIndex, targetState);
            
            // Explicitly set the clicked checkbox (since we prevented default)
            checkbox.checked = targetState;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            // Regular click - remember this as the anchor for next shift+click
            this.lastCheckedIndex = currentIndex;
        }

        // Update master checkbox state
        this.updateMaster();
    }

    /**
     * Handle master checkbox change - select or deselect all based on its state.
     *
     * @param {Event} event - Change event from master checkbox
     */
    toggleAll(event) {
        const checked = event.currentTarget.checked;

        this.checkboxTargets.forEach(checkbox => {
            if (checkbox.checked !== checked) {
                checkbox.checked = checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    /**
     * Update master checkbox state based on row checkboxes.
     * - Checked if all are checked
     * - Unchecked if none are checked
     * - Indeterminate if some (but not all) are checked
     */
    updateMaster() {
        if (!this.hasMasterTarget) return;

        const total = this.checkboxTargets.length;
        const checked = this.checkboxTargets.filter(cb => cb.checked).length;

        this.masterTarget.checked = checked === total && total > 0;
        this.masterTarget.indeterminate = checked > 0 && checked < total;
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
            const checkbox = this.checkboxTargets[i];
            if (checkbox) {
                checkbox.checked = checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    }

}
