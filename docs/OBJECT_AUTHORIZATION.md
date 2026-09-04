# Object-Level Authorization

`#[Admin(permissions: [...])]` and `AdminEntityVoter` answer "may this user perform `ADMIN_EDIT` on the `Contact` entity **type** at all" — a class-level check using the entity's short name as the voter subject. Object-level authorization answers the narrower question: "may this user perform `ADMIN_EDIT` on **this particular** `Contact` **instance**." It runs the loaded (or freshly submitted, for new/edit) entity itself through Symfony's normal `AuthorizationCheckerInterface`, so an ordinary application-defined voter gets a say.

Typical use case: `ROLE_SALES` may edit customer contacts, `ROLE_PURCHASING` may edit vendor contacts, both may edit contacts of type `BOTH`. Class-level permissions can't express that distinction — object-level authorization can, without any admin-bundle-specific interface on your voter.

## Table of Contents

- [Quick Start](#quick-start)
- [Where Checks Run](#where-checks-run)
- [Writing a Voter](#writing-a-voter)
- [Why the New/Edit Form Check Lives in doSubmitForm(), Not save()](#why-the-newedit-form-check-lives-in-dosubmitform-not-save)
- [CSRF Runs Before Authorization](#csrf-runs-before-authorization)
- [Batch Actions](#batch-actions)
- [Row-Action Button Visibility](#row-action-button-visibility)
- [Inline Editing](#inline-editing)
- [Two Things Worth Understanding Before You Turn This On](#two-things-worth-understanding-before-you-turn-this-on)
- [What This Does Not Cover](#what-this-does-not-cover)
- [Testing](#testing)

## Quick Start

**1. Opt the entity in:**

```php
use Kachnitel\AdminBundle\Attribute\Admin;

#[Admin(label: 'Contacts', enableObjectAuth: true)]
class Contact
{
    public function getType(): ContactType { /* ... */ }
}
```

**2. Register a plain Symfony voter that supports the entity as subject:**

```php
use App\Entity\Contact;
use App\Enum\ContactType;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ContactVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Contact
            && in_array($attribute, ['ADMIN_SHOW', 'ADMIN_NEW', 'ADMIN_EDIT', 'ADMIN_DELETE', 'ADMIN_ARCHIVE'], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /** @var Contact $contact */
        $contact = $subject;
        $roles   = $token->getRoleNames();

        return match ($contact->getType()) {
            ContactType::CUSTOMER => in_array('ROLE_SALES', $roles, true),
            ContactType::VENDOR   => in_array('ROLE_PURCHASING', $roles, true),
            ContactType::BOTH     => in_array('ROLE_SALES', $roles, true) || in_array('ROLE_PURCHASING', $roles, true),
        } || in_array('ROLE_ADMIN', $roles, true);
    }
}
```

Auto-registered as a service the normal Symfony way (autowire + autoconfigure picks up the `Voter` base class via its `security.voter` tag). **Do this before shipping step 1** — see the first gotcha below for what happens if you don't.

## Where Checks Run

Object-level authorization touches two conceptually different kinds of call site, and it's worth keeping them separate rather than reading this as "N equally-weighted security boundaries":

- **Enforcement** — the check is the reason a mutation or a read is actually blocked. Bypassing the UI (a forged LiveComponent request, a hand-crafted POST) still hits one of these.
- **Display-only** — the check only decides whether a button, action, or trigger is shown. It never *itself* stops a mutation; real enforcement for that same operation lives at one of the enforcement call sites below. These exist purely so a user doesn't see an option they'd be denied on click.

The full, current inventory of both lives as a docblock on `ObjectAuthorizationChecker` itself (`src/Security/ObjectAuthorizationChecker.php`) — treat that as the source of truth and this table as a narrated version of it. Each enforcement row runs after the entity is loaded (or, for new/edit saves, after the submitted data has been bound onto it) and, where CSRF applies, after CSRF has already been validated — see [CSRF Runs Before Authorization](#csrf-runs-before-authorization).

### Enforcement

| Location | Voter attribute | Runs against |
|---|---|---|
| `AbstractAdminController::doShow()` | `ADMIN_SHOW` | The loaded entity |
| `AbstractAdminController::doEdit()` | `ADMIN_EDIT` | The loaded entity |
| `AbstractAdminController::doDeleteEntity()` | `ADMIN_DELETE` | The loaded entity, after CSRF validation, before removal |
| `GenericAdminController::archive()` / `unarchive()` | `ADMIN_ARCHIVE` | The loaded entity, after CSRF validation, before the field mutation |
| The New/Edit LiveComponent form save, and the "+ Add" inline-creation dialog (`AdminFormComponentTrait::doSubmitForm()`) | `ADMIN_NEW` or `ADMIN_EDIT` | The entity **after** form binding — see [below](#why-the-newedit-form-check-lives-in-dosubmitform-not-save) for exactly where and why |
| `AdminEditabilityResolver::canEdit()` | `ADMIN_EDIT` | The entity, immediately before the inline-edited property's new value is written — see [Inline Editing](#inline-editing) for a real timing caveat this one has |
| Batch archive / batch delete (`ArchiveButton::execute()`, `EntityListBatchService::batchDelete()`) | `ADMIN_ARCHIVE` / `ADMIN_DELETE` | Each selected entity individually, via `SkipsUnauthorizedEntitiesTrait` — see [Batch Actions](#batch-actions) |

### Display-only

| Location | What it hides | Real enforcement lives at |
|---|---|---|
| `EntityList::editRow()` | Prevents a denied row from visually entering edit mode | `AdminEditabilityResolver::canEdit()`, consulted independently by each field component |
| `RowActionVisibilityChecker` | Hides Edit/Delete/Archive/Unarchive row-action buttons for a denied row | The corresponding controller/batch action above |

A custom controller extending `AbstractAdminController` directly (rather than `GenericAdminController`) gets the *enforcement* checks above for free, since they live on the parent class. It does **not** get the bundle's existing *class-level* check (`AdminEntityVoter` via `checkEntityPermission()`) for free — that call has only ever lived in `GenericAdminController`'s own route methods, not in `AbstractAdminController`, and this feature doesn't change that. If you're routing through your own controller, you're responsible for your own class-level check exactly as before; object-level authorization is additive to whatever you already do, not a replacement for it.

## Writing a Voter

No bundle-specific interface is required for the voter itself — it's a plain `Symfony\Component\Security\Core\Authorization\Voter\Voter`, exactly as you'd write for any other part of your app. The only two things to get right:

1. **`supports()` must accept the entity instance as `$subject`.** Not the short class name — an actual object, checked with `instanceof`.
2. **`supports()` should list every `ADMIN_*` attribute you want this voter to have an opinion on.** If you only care about edit/delete and don't list `ADMIN_SHOW`, the show page will fall through to whatever else is registered (see the gotcha below — if nothing else supports the object subject, that's a deny).

## Why the New/Edit Form Check Lives in doSubmitForm(), Not save()

`AdminFormSaveTrait::save()` is a documented, supported override point — `FORMS.md`'s "Custom form components" section describes overriding it entirely, with your own persist/flush logic and no call back into the trait's implementation, as fully supported. A check written directly in `save()`'s own body would be silently skipped by any component exercising that documented pattern, which is exactly the kind of gap a security check can't have.

Instead, the check lives in `AdminFormComponentTrait::doSubmitForm()` — the one call every save flow, default or overridden, has to make to retrieve the bound entity in the first place. There's no other way to read live-synced form data, which is what makes this the genuinely non-bypassable integration point. Components opt in by implementing `ObjectAuthorizedFormInterface`, a single method returning the attribute to check:

```php
public function getObjectAuthorizationAttribute(): string
{
    return $this->entityId === null ? AdminEntityVoter::ADMIN_NEW : AdminEntityVoter::ADMIN_EDIT;
}
```

`AdminEntityForm` and `InlineEntityForm` — the bundle's own components — implement this already; you don't need to do anything for the standard New/Edit page or the inline-add dialog. A fully custom component that composes `AdminFormComponentTrait` + `AdminFormSaveTrait` directly (per `FORMS.md`'s `PurchaseOrderForm` example) does **not** get this for free — implement `ObjectAuthorizedFormInterface` yourself to opt in, or call `ObjectAuthorizationChecker::denyAccessUnlessGranted()` wherever suits your component. Either way this is safe to do unconditionally: the checker itself remains a no-op for any entity without `enableObjectAuth: true`.

The check runs against the entity's **post-submission** state, not the state it had when the page loaded — which is what makes "an edit cannot change an entity into a type the user isn't allowed to manage" actually enforceable. A `Contact` that starts as `CUSTOMER` (editable by `ROLE_SALES`) but whose edit changes it to `VENDOR` is denied on that same submission.

## CSRF Runs Before Authorization

In `doDeleteEntity()` and `archive()`/`unarchive()`, CSRF is validated **before** object-level authorization runs. This is a deliberate ordering, not an incidental one: checking authorization first would mean a request with no valid CSRF token at all could still distinguish "denied" (403) from "CSRF was about to fail anyway" (400) based on whether the current user has object-level access to that specific row — a permission oracle that lets an attacker enumerate accessible rows without ever presenting a valid token. Validating CSRF first closes that: an invalid token always produces the same 400 regardless of the user's authorization state for that row.

## Batch Actions

`ArchiveButton` and `DeleteButton` (the batch archive/delete components behind the "Archive Selected" / "Delete Selected" buttons) check object-level authorization **per selected entity**, individually, rather than gating the batch as a whole. Both go through the same helper — `SkipsUnauthorizedEntitiesTrait::filterAuthorizedEntities()` — rather than each implementing their own find-then-filter loop:

```php
$resolved = $this->resolveByIds($repository, $selectedIds); // find() each ID, drop the nulls
$granted  = $this->filterAuthorizedEntities($this->objectAuthChecker, AdminEntityVoter::ADMIN_DELETE, $resolved);
// act on array_values($granted); array_keys($granted) is what stays "processed"
```

This lives as a trait consumed by both components, rather than as a method on `ObjectAuthorizationChecker` itself, deliberately: the trait's loop only ever calls `ObjectAuthorizationChecker::isGranted()`, the one method every existing consumer's tests already mock. A batch-specific method placed directly on the checker would need its own mock stub in every test that constructs a full mock of the checker — easy to forget, and it fails silently (an unstubbed method with an `array` return type returns `[]`, not an error) rather than loudly.

Behaviourally:

- An entity the current user is authorized for is processed normally.
- An entity they're **not** authorized for is silently skipped — the rest of the batch still goes through. This mirrors the existing, pre-authorization behaviour for an ID that doesn't resolve to an entity at all (already deleted, bad ID), which has always been skipped rather than aborting the whole request.
- The `admin:action:completed` event (which `EntityList` uses to clear processed rows from the selection and refresh the query) reports only the entities that were **actually** processed — not the original full selection. A denied row is never added to that list, so after the list refreshes it's still selected and still visible, rather than silently vanishing from the count with no signal that it didn't go through.

There's currently no toast or explicit "3 of 10 skipped" message — the signal is that denied rows remain selected. If your application needs an explicit per-item report, the array `filterAuthorizedEntities()` returns (or its `array_keys()`) is the value to build that from — `EntityListBatchService::batchDelete()` returns exactly that.

If you're adding a new batch action, `use SkipsUnauthorizedEntitiesTrait;` and call `filterAuthorizedEntities()` the same way rather than writing a third find-then-filter loop.

## Row-Action Button Visibility

Edit / Delete / Archive / Unarchive row-action buttons — in list rows, and on show/edit page headers — are filtered through object-level authorization the same way class-level permissions already filter them. `RowActionVisibilityChecker::isVisible()` reuses each action's own `voterAttribute` (the same `AdminEntityVoter::ADMIN_*` constant `ObjectAuthorizationChecker` itself expects) against the specific entity instance being rendered, so a user who would be denied on click doesn't see the button at all.

This applies to any row action carrying a `voterAttribute` — the bundle's own Show/Edit/Archive/Unarchive buttons, and any `#[AdminAction(voterAttribute: '...')]` you declare yourself. An action with no `voterAttribute` set is unaffected (there's nothing to check it against). As with everywhere else in this feature, this is a no-op for entities without `enableObjectAuth: true`. This is display-only — see [Where Checks Run](#where-checks-run) — the real enforcement is whatever controller or batch action the button links to.

Custom action buttons rendered via `liveComponent:` still enforce their own access inside the component itself, same as before — this only affects whether the button is offered in the first place.

## Inline Editing

List-view inline editing (see [Inline Editing](INLINE_EDIT.md)) goes through `AdminEditabilityResolver::canEdit()` — this bundle's implementation of `entity-components-bundle`'s `EditabilityResolverInterface`. That single method is called by every inline-edit field component in exactly two places: to decide whether to render the ✎ trigger, and — the enforcement call — inside `save()`, before any value is written. Object-level authorization is checked there, immediately after the existing class-level `ADMIN_EDIT` voter check, so `canEdit()` is itself an **enforcement** call site, not a display-only one, even though one of its two callers is a rendering decision.

`EntityList::editRow()` — the row-level "enter edit mode" action fired by the ✏️ button — also checks object-level authorization, but purely as a UX convenience and is listed as **display-only** above: it stops a denied row from visually entering an edit state with nothing actually editable in it. It has no bearing on security by itself — each field component enforces independently via `AdminEditabilityResolver::canEdit()` regardless of whether a row is "in edit mode," so this would still be safe even if `editRow()` checked nothing at all.

### Checked before the write, not after

`canEdit()` being enforcement doesn't mean it's timing-perfect. Unlike the New/Edit form flow, this has a real limitation. `entity-components-bundle`'s `AbstractEditableField::save()` runs `canEdit()` guard → write → validate → flush, with no second authorization check between the write and the flush. `canEdit()` therefore validates the entity's state as it was **before** this specific field's new value is applied.

If the property being inline-edited is the same one your voter's decision reads — say, `Contact::$type` itself — the check passes or fails based on the *old* type, and the new type is written without a further check. A `ROLE_SALES` user editing a `CUSTOMER` contact's `type` field to `VENDOR` would be authorized for that specific save (the check ran against `CUSTOMER`), even though a `VENDOR` contact wouldn't otherwise be editable by them at all.

This can't be fixed from `FrdAdminBundle` — the check/write ordering lives inside `entity-components-bundle`'s `AbstractEditableField::save()`, a separate package with its own release cycle. There's also no clean per-field lever to say "editable via the form but not inline": `#[AdminColumn(editable: false)]` excludes a field from *both* surfaces, since `AdminColumnEditabilityResolver` (the sibling resolver that decides form-field inclusion) treats an explicit `false` the same way. So the honest mitigation, until the ordering changes upstream, is to keep any field your voter reads out of inline editing entirely — either via `#[AdminColumn(editable: false)]` (accepting it becomes uneditable through the admin UI, full stop) or by leaving `enableInlineEdit` off for that entity — or to design the voter so its decision doesn't key off a field this feature can mutate.

## Two Things Worth Understanding Before You Turn This On

Both of the following come from the same underlying Symfony mechanism, applied at two different scopes. Understanding one makes the other obvious, but they bite in different ways, so both are called out explicitly here rather than one being a footnote to the other.

### Why this is opt-in, not automatic for every entity

Symfony's default `AccessDecisionManager` (affirmative strategy, `allow_if_all_abstain: false`) **denies** when every registered voter abstains on a given attribute/subject pair. `AdminEntityVoter` — the bundle's own class-level voter — only supports **string** subjects; it always abstains when the subject is an object. If object-level checks ran unconditionally for every entity, then for any entity without an application voter that supports its object subject, *every single request* — every show, edit, new, delete, archive — would abstain-cascade straight to a 403, with no error at boot and no code change on the application's part. That would be a silent, sweeping regression for every existing installation the moment this feature shipped, including entities nobody ever intended to protect this way.

That's the whole reason `enableObjectAuth` exists as an explicit, per-entity, default-`false` flag: it scopes the abstain-cascade risk to only the entities you deliberately opt in, leaving every other entity's behavior — including every entity in a bundle release that predates this feature — completely unchanged.

### What happens if you opt an entity in without writing a voter

Flip the same mechanism around and point it at a single entity: set `enableObjectAuth: true` on `Contact`, but don't register `ContactVoter` (or register it with a `supports()` that doesn't actually match `Contact` — a typo'd `instanceof` check has the identical effect). Now `AdminEntityVoter` still abstains (object subject), and *nothing else* votes on `Contact` instances either. All voters abstain, `allow_if_all_abstain` is `false`, so the decision is a **deny** — not a pass-through, not a warning, not an error at boot. Every show, edit, new, delete, and archive/unarchive for `Contact` starts returning 403, indistinguishable from a real permission problem.

There's no validation today that catches this at boot time or in the admin UI — turning the flag on is enough to start denying everything for that entity until a matching voter exists. Treat "add the voter" as part of the same change as "flip the flag," not a follow-up step, and if requests for a freshly-opted-in entity start 403ing unexpectedly, a missing or mis-scoped voter is the first thing to check.

## What This Does Not Cover

- **Index/list filtering.** Object-level authorization gates actions against a specific, already-identified entity — it has no bearing on which rows a list query returns. If you need per-row visibility in list views (hide rows entirely rather than just disabling their actions), that's a separate concern, e.g. a custom Doctrine query filter.
- **Fully custom form components that don't implement `ObjectAuthorizedFormInterface`.** See [Why the New/Edit Form Check Lives in doSubmitForm(), Not save()](#why-the-newedit-form-check-lives-in-dosubmitform-not-save) — a from-scratch component composing `AdminFormComponentTrait` directly has to opt in explicitly.
- **An explicit per-item report for partial batch results.** See [Batch Actions](#batch-actions) — the signal today is "denied rows stay selected," not a message naming which ones or why.
- **Inline-edit checks run before the write, not after.** See [Inline Editing → Checked before the write, not after](#checked-before-the-write-not-after) — if the edited field is itself what your voter's decision depends on, the check validates the old value.
- **Boot-time or console validation that a voter actually exists for an opted-in entity.** The second gotcha above (deny-by-default with no matching voter) currently surfaces only as a 403 at request time. Worth having eventually; not built yet.

## Testing

A voter like `ContactVoter` is tested exactly like any other Symfony voter — no bundle-specific test helpers involved. For the admin-bundle side of the integration (does `save()`/`archive()`/a batch action actually call it with the right attribute and entity, at the right point in the request), functional-test coverage against a real LiveComponent kernel or controller test double is the reliable way to verify a deny actually blocks persistence — see this bundle's own `--group object-authorization` test suite for a worked example, including a dedicated regression test proving the check survives a fully-overridden `save()`.

---

**See also:** [Configuration](CONFIGURATION.md) · [Row Actions](ROW_ACTIONS.md) · [Batch Actions](BATCH_ACTIONS.md) · [Forms](FORMS.md) · [Inline Add](INLINE_ADD.md)
