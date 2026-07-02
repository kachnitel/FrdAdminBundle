/**
 * Minimal DOM fixture helpers for Stimulus controller tests.
 *
 * `@symfony/stimulus-testing` (which used to provide these same two helpers)
 * has been deprecated by the Symfony UX team — it pulled in Jest, jsdom and
 * Testing Library as forced dependencies. Their own migration guide replaces
 * it with this exact pattern: a tiny local helper file, used alongside
 * `@testing-library/dom` directly.
 *
 * @see https://github.com/symfony/stimulus-testing
 */

/**
 * Append an HTML fixture to the document body and return its wrapper element.
 *
 * @param {string} html
 * @returns {HTMLDivElement}
 */
export function mountDOM(html = '') {
    const container = document.createElement('div');
    container.innerHTML = html;
    document.body.appendChild(container);
    return container;
}

/**
 * Remove all fixtures previously added via mountDOM().
 */
export function clearDOM() {
    document.body.innerHTML = '';
}
