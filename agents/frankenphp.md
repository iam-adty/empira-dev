# AGENTS.md

> **Status: describes shipped code.** Current behaviour, verified against `14
> tests, 24 assertions` — none of them running an actual worker.

Guidance for AI agents and new contributors working on the Empira FrankenPHP
module.

## What this is

`iam-adty/empira-frankenphp` — worker mode for [Empira](../../principle), as a
**module**. The framework does not ship it, does not require it, and runs without
it.

```
namespace Empira\FrankenPhp\;    PHP ^8.4, MIT
```

It is a module because the worker loop used to live in every application's
`public/index.php`, which branched on `APP_RUNTIME` and sat there either way. An
application on nginx carried a FrankenPHP dependency it never ran on, and deleting
half the file was a thing you had to remember.

## What this package owns, and what it does not

**Owns the loop.** `worker.php` and `FrankenPhpModule::handleForever()` — the
`frankenphp_handle_request` loop, the `gc_collect_cycles()`, the `MAX_REQUESTS`
bound.

**Explains why a base image is unavoidable, but does not ship one.** FrankenPHP
cannot be added to a stock PHP the way `pdo_sqlite` can: the extension must exist
before PHP starts, so there is no runtime `-d` flag that substitutes for it. That
constraint is real and is why this package exists at all rather than the loop
being a copy-paste — but *whose* image that is remains an application and
deployment decision. See "Running things".

**Does not own the framework.** `principle/src/` contains no FrankenPHP code. Do not
add any. The framework's per-request resets in `Application::handle()` run whether
or not a worker is present — that is what lets the same application run under both
— and a change that made them conditional on the runtime would undo that.

**Does not own the reference application.** `app/` installs neither this package
nor a loop. Adding `FrankenPhpModule` to its `bootstrap/modules.php` is a change
to the app, and an app decision.

## The refusal

`FrankenPhpModule::register()` throws if `frankenphp_handle_request` does not
exist:

```php
if (! self::frankenPhpIsAvailable()) {
    throw new \LogicException(/* … what to do instead … */);
}
```

**Keep this.** It looks like it changes nothing — the framework resets correctly
under php-fpm anyway — and that is exactly why it matters. An application that
believes it is in a worker and is not has a session and an identity map that look
handled, until the second request lands on the same thread and is served the first
one's state. Nothing fails until then.

Detection asks whether the function exists, not what a version string says, because
the function is the only thing the loop calls. It also makes the suite honest: the
tests run on a host PHP — deliberately the wrong runtime — so they test the
failure mode rather than pretending to reach the happy path.

## Running things

```bash
composer install
composer test           # 14 tests
```

**There is no image, and adding one is not a small change.** This package ships no
`Dockerfile` and no compose file. One existed and was removed: it built an
application that does not install this package, so the entrypoint it named was
never created, and an image nobody has ever built is worse than no image in a
published package. This workspace now ships no container artifacts at all.

Producing a FrankenPHP image is an application and deployment decision — what else
goes in it, what runs beside it, where it is built. A stock PHP cannot become
FrankenPHP at runtime the way an ordinary extension can be loaded with `-d`
afterwards, so it has to exist before PHP starts.

If you write one, two things have to be true or it will not start:

- The application **requires `iam-adty/empira-frankenphp`**. The reference `app/`
  deliberately does not, so an image of that app has no `worker.php` to point at.
- `worker.php` is run **from the application root**, because that is where it looks
  for `vendor/autoload.php` and `bootstrap/app.php`.

## Things that will waste your time

- **`$var` in a compose `command` block is Compose's**, not the shell's. Compose
  interpolates it before `sh` sees it, so a shell variable arrives empty and the
  error is about something else entirely — a missing dynamic library, usually.
  Escape it: `$$var`. Nothing in this workspace uses Compose any more, but the
  trap applies to anyone who does add it back.
- **`worker.php` must run from the application root.** It resolves
  `vendor/autoload.php` and `bootstrap/app.php` against the working directory,
  because it has two possible locations — `app/vendor/iam-adty/empira-frankenphp/`
  when installed, a sibling checkout when developing — and those share nothing to
  be relative to. Searching upward from the file instead finds *this package's*
  own `vendor/` in the sibling layout and loads the application's dependencies from
  the wrong install, silently. `EMPIRA_AUTOLOAD` / `EMPIRA_BOOTSTRAP` override it.
- **`APP_RUNTIME` does not exist here.** It was a branch in the app's front
  controller, which is why that file no longer has a branch.
- **Worker mode is not the default.** `app/` is served by `php -S`, and that is
  what the README and the root guidance describe. Do not "restore" FrankenPHP as
  the app's runtime.
- **Uninstalling this package must be a deletion, not an edit.** It is optional by
  construction. Anything that makes an application depend on it to run has broken
  the premise.
- **Nothing here has ever run a worker.** The loop, the entrypoint's path
  resolution and the module's refusal are all covered by tests; no FrankenPHP
  process has served a request from this package. Treat "it works under a real
  worker" as unproven.

## The boundary with `principle`

Same as every module: **name a contract, not an internal.** The framework keeps
`Module` and `MigrationSource`; this package implements `Module` and calls
`Application::respond()`. If a change here seems to need a new framework method,
the question is whether the framework can answer it *without knowing what a worker
is* — usually it can, and usually the honest answer is a narrower contract.

`dev/tools/module-contract` checks that this package is a `library` requiring
`empira-principle` and not the application. Run it from the workspace root.

## Then

- [`principle/docs/workers.md`](../../principle/docs/workers.md) — why workers leak,
  and the rules for your own code.
- [`../auth/agents`](../../auth) — the other module, and the worked example for
  everything a module is.