import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for date range filter inputs.
 * Combines two date inputs (from/to) into a JSON string value.
 */
export default class extends Controller {
    static targets = ['from', 'to', 'output'];

    connect() {
        // Initialize the output field on connect
        this.update();
    }

    /**
     * Update the hidden output field with combined JSON value.
     * Triggers a change event to notify Live Components.
     */
    update() {
        const from = this.fromTarget.value;
        const to = this.toTarget.value;

        // Only create JSON if at least one value is set
        let value = '';
        if (from || to) {
            value = JSON.stringify({ from: from || null, to: to || null });
        }

        this.outputTarget.value = value;
        this.outputTarget.dispatchEvent(new Event('change', { bubbles: true }));
        this.outputTarget.dispatchEvent(new Event('input', { bubbles: true }));
    }
}
