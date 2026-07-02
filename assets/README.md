# Kachnitel Admin Bundle - Assets

This directory contains Stimulus controllers for the admin bundle.

## Structure

```
assets/
├── controllers/           # Source Stimulus controllers
│   ├── batch-select_controller.js
│   └── admin-inline-add_controller.js
├── dist/                  # Build output for AssetMapper
│   └── batch-select_controller.js
├── test/                  # Vitest unit tests, mirrors controllers/
│   ├── stimulus-helpers.js
│   └── controllers/
│       ├── batch-select_controller.test.js
│       └── admin-inline-add_controller.test.js
├── package.json           # UX bundle configuration
└── README.md
```

## AssetMapper Support

The bundle automatically registers its Stimulus controllers with AssetMapper when available. The controllers are exposed under the `@kachnitel/admin-bundle` namespace.

### In Templates

Controllers are used via the standard Stimulus naming convention:

```twig
<div data-controller="kachnitel--admin-bundle--batch-select">
    <!-- ... -->
</div>
```

Or using the `stimulus_controller()` Twig function:

```twig
<div {{ stimulus_controller('kachnitel/admin-bundle/batch-select') }}>
    <!-- ... -->
</div>
```

## Webpack Encore Support

For Webpack Encore users, the bundle also works seamlessly. The controllers are exposed via the same package.json configuration.

## Testing

Controller unit tests run on [Vitest](https://vitest.dev/) with [`@testing-library/dom`](https://testing-library.com/docs/dom-testing-library/intro/) and jsdom — no browser required.

```bash
cd assets
npm install   # first time only
npm test          # run once
npm run test:watch  # re-run on file changes
```

### Running one controller's tests (feature groups)

Each controller has its own test file (`test/controllers/{name}_controller.test.js`), so filtering by that name is the JS equivalent of PHPUnit's `--group`:

```bash
npm test -- batch-select
npm test -- admin-inline-add
```

### Writing a test for a new controller

Tests live under `test/controllers/`, mirroring the path of the controller under `controllers/`. `test/stimulus-helpers.js` provides `mountDOM()`/`clearDOM()` fixture helpers — this replaces the official `@symfony/stimulus-testing` package, which Symfony deprecated in favor of each project picking its own runner. Because Stimulus connects controllers via a `MutationObserver`, always wait for connection (e.g. via `@testing-library/dom`'s `waitFor`, polling `application.getControllerForElementAndIdentifier(...)`) before asserting — see `batch-select_controller.test.js` for the pattern.

### Testing gotchas

A few non-obvious jsdom/Stimulus interactions, discovered while testing `admin-inline-add` (which listens on `window`, throws from an action method, and calls native `<dialog>` methods):

- **`application.stop()` does not call `disconnect()`.** It only stops the `MutationObserver` from processing future changes — controllers already connected keep running, including any raw `window.addEventListener()` calls made in `connect()`. Since Vitest's jsdom environment shares one `window` per test file, an undisconnected controller's listener stays live for every later test. Always `clearDOM()` (or otherwise remove the controller's root element) **before** calling `application.stop()`, and `await waitFor(() => { if (application.controllers.length > 0) throw new Error(...) })` so the real mutation-driven `disconnect()` has actually run before moving on.
- **Exceptions thrown by an action method never reach `window`'s `'error'` event.** Stimulus's `Dispatcher` wraps every action invocation in its own `try/catch` and reports failures via `Application#handleError`, which calls `window.onerror(...)` as a plain function call — not `dispatchEvent(new ErrorEvent(...))`. To assert on a controller-thrown error, stub `window.onerror` directly; `addEventListener('error', ...)` will not fire.
- **Don't let Stimulus's default logger touch `console.error` with a live DOM element.** `Application#handleError`'s default `logger` is `console`, and it formats failures via `console.error('%o', error, detail)` — `detail` includes the DOM element from the action context, and Node's `util.inspect()` on a jsdom element can hit a real jsdom `CSSStyleSheet` binding bug and crash the test run with an unrelated `TypeError`. Stub `application.logger` with no-op `error`/`warn`/`log`/`groupCollapsed`/`groupEnd` methods in `beforeEach` for any suite where a controller might throw or log — see `admin-inline-add_controller.test.js`.
- **jsdom does not implement `HTMLDialogElement.showModal()`/`close()`** — both are `undefined` on the element (not merely no-ops). Assign `vi.fn()` stubs to the mounted `<dialog>` element in tests that call either method.

## Development

When making changes to controllers:

1. Edit the source file in `controllers/`
2. Add or update its test under `test/controllers/` and run `npm test`
3. Copy to `dist/` (for now, manual copy - automated build coming soon)
4. Clear cache in consuming applications: `php bin/console cache:clear`
5. For AssetMapper apps, run: `php bin/console importmap:update`

## Controllers

### batch-select

Multi-select controller with keyboard modifier support:
- **Click**: Toggle individual selection
- **Shift+Click**: Range selection
- **Ctrl/Cmd+Click**: Multi-toggle

Used by the EntityList component when `enableBatchActions: true`.
