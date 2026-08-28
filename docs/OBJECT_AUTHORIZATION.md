# Object-Level Authorization

`#[Admin(permissions: [...])]` and `AdminEntityVoter` answer "may this user perform `ADMIN_EDIT` on the `Contact` entity **type** at all" — a class-level check using the entity's short name as the voter subject. Object-level authorization answers the narrower question: "may this user perform `ADMIN_EDIT` on **this particular** `Contact` **instance**." It runs the loaded (or freshly submitted, for new/edit) entity itself through Symfony's normal `AuthorizationCheckerInterface`, so an ordinary application-defined voter gets a say.

Typical use case: `ROLE_SALES` may edit customer contacts, `ROLE_PURCHASING` may edit vendor contacts, both may edit contacts of type `BOTH`. Class-level permissions can't express that distinction — object-level authorization can, without any admin-bundle-specific interface on your side.

## Table of Contents

- [Quick Start](#quick-start)
- [Where Checks Run](#where-checks-run)
- [Writing a Voter](#writing-a-voter)
- [Two Things Worth Understanding Before You Turn This On](#two-things-worth-understanding-before-you-turn-this-on)
- [What This Does Not Cover](#what-this-does-not-cover)
- [Testing](#testing)

## Quick Start

**1. Opt the entity in:**

```php
use Kachnitel\AdminBundle\Attribute\Admin;

#[Admin(label: 'Contacts', enableObjectAuthorization: true)]
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

Object-level authorization is checked in five places, each after the entity is loaded (or, for new/edit saves, after the submitted data has been bound onto it) and before any further action is taken:

| Location | Voter attribute | Runs against |
|---|---|---|
| `AbstractAdminController::doShow()` | `ADMIN_SHOW` | The loaded entity |
| `AbstractAdminController::doEdit()` | `ADMIN_EDIT` | The loaded entity |
| `AbstractAdminController::doDeleteEntity()` | `ADMIN_DELETE` | The loaded entity, before removal |
| `GenericAdminController::archive()` / `unarchive()` | `ADMIN_ARCHIVE` | The loaded entity, before the field mutation and before CSRF validation |
| `AdminFormSaveTrait::save()` (the New/Edit LiveComponent form) | `ADMIN_NEW` or `ADMIN_EDIT` | The entity **after** form binding, before `persist()` |
| `InlineEntityForm::save()` (the "+ Add" dialog) | `ADMIN_NEW` | The entity after form binding, before `persist()` |

The last two are what make "an edit cannot change an entity into a type the user isn't allowed to manage" actually enforceable: the check runs against the entity's state *after* the submitted data has been applied, not the state it had when the page loaded. A `Contact` that starts as `CUSTOMER` (editable by `ROLE_SALES`) but whose edit changes it to `VENDOR` is denied on that same submission — not silently saved and only caught later.

A custom controller extending `AbstractAdminController` directly (rather than `GenericAdminController`) picks up the three controller-level checks above for free, since they live on the parent class. It does **not** get the bundle's existing *class-level* check (`AdminEntityVoter` via `checkEntityPermission()`) for free — that call has always lived specifically in `GenericAdminController`'s route methods, not in `AbstractAdminController`, and this feature doesn't change that. If you're routing through your own controller, you're responsible for your own class-level check exactly as before; object-level authorization is additive to whatever you already do, not a replacement for it.

Batch actions (`ArchiveButton` / `DeleteButton`, which act on many entities server-side per click) are **not yet covered** — partial-batch-denial semantics (deny the whole batch? skip denied rows silently? report which rows failed?) need their own design discussion. Row-action buttons in the list UI are also not filtered based on object-level authorization — a user may still see an Edit/Delete button for a row they can't actually open, and get a 403 on click. Both are tracked as follow-ups, not silent gaps.

## Writing a Voter

No bundle-specific interface is required — this is a plain `Symfony\Component\Security\Core\Authorization\Voter\Voter`, exactly as you'd write for any other part of your app. The only two things to get right:

1. **`supports()` must accept the entity instance as `$subject`.** Not the short class name — an actual object, checked with `instanceof`.
2. **`supports()` should list every `ADMIN_*` attribute you want this voter to have an opinion on.** If you only care about edit/delete and don't list `ADMIN_SHOW`, the show page will fall through to whatever else is registered (see the gotcha below — if nothing else supports the object subject, that's a deny).

## Two Things Worth Understanding Before You Turn This On

Both of the following come from the same underlying Symfony mechanism, applied at two different scopes. Understanding one makes the other obvious, but they bite in different ways, so both are called out explicitly here rather than one being a footnote to the other.

### Why this is opt-in, not automatic for every entity

Symfony's default `AccessDecisionManager` (affirmative strategy, `allow_if_all_abstain: false`) **denies** when every registered voter abstains on a given attribute/subject pair. `AdminEntityVoter` — the bundle's own class-level voter — only supports **string** subjects; it always abstains when the subject is an object. If object-level checks ran unconditionally for every entity, then for any entity without an application voter that supports its object subject, *every single request* — every show, edit, new, delete, archive — would abstain-cascade straight to a 403, with no error at boot and no code change on the application's part. That would be a silent, sweeping regression for every existing installation the moment this feature shipped, including entities nobody ever intended to protect this way.

That's the whole reason `enableObjectAuthorization` exists as an explicit, per-entity, default-`false` flag: it scopes the abstain-cascade risk to only the entities you deliberately opt in, leaving every other entity's behavior — including every entity in a bundle release that predates this feature — completely unchanged.

### What happens if you opt an entity in without writing a voter

Flip the same mechanism around and point it at a single entity: set `enableObjectAuthorization: true` on `Contact`, but don't register `ContactVoter` (or register it with a `supports()` that doesn't actually match `Contact` — a typo'd `instanceof` check has the identical effect). Now `AdminEntityVoter` still abstains (object subject), and *nothing else* votes on `Contact` instances either. All voters abstain, `allow_if_all_abstain` is `false`, so the decision is a **deny** — not a pass-through, not a warning, not an error at boot. Every show, edit, new, delete, and archive/unarchive for `Contact` starts returning 403, indistinguishable from a real permission problem.

There's no validation today that catches this at boot time or in the admin UI — turning the flag on is enough to start denying everything for that entity until a matching voter exists. Treat "add the voter" as part of the same change as "flip the flag," not a follow-up step, and if requests for a freshly-opted-in entity start 403ing unexpectedly, a missing or mis-scoped voter is the first thing to check.

## What This Does Not Cover

- **Batch actions** — not yet gated. See "Where Checks Run" above.
- **List-view row-action visibility** — Edit/Delete/Archive buttons render based on class-level permissions only; a denied object-level action is only surfaced when clicked, not hidden in advance.
- **Index/list filtering** — object-level authorization has no bearing on which rows appear in a list query. It only gates actions against a specific, already-identified entity. If you need per-row visibility in list views, that's a separate concern (e.g. a custom Doctrine query filter), not something this feature does.

## Testing

A voter like `ContactVoter` is tested exactly like any other Symfony voter — no bundle-specific test helpers involved. For the admin-bundle side of the integration (does `save()`/`archive()`/etc. actually call it with the right attribute and entity, at the right point in the request), functional-test coverage against a real LiveComponent kernel or controller test double is the reliable way to verify a deny actually blocks persistence — see this bundle's own `--group object-authorization` test suite for a worked example (a fixture entity, a fixture voter, and both the controller-level and LiveComponent-level integration points covered).

---

**See also:** [Configuration](CONFIGURATION.md) · [Row Actions](ROW_ACTIONS.md) · [Forms](FORMS.md) · [Inline Add](INLINE_ADD.md)
