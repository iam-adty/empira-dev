# AGENTS.md

> **Status: describes shipped code.** Current behaviour, verified against `6 tests,
> 11 assertions`.

Guidance for AI agents and new contributors working on the Empira FrankenPHP
module.

## What this is

`iam-adty/empira-frankenphp` — worker mode for [Empira](../principle), as a
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

**Owns a base image.** `Dockerfile`, because FrankenPHP cannot be added to a stock
PHP the way `pdo_pgsql` can: the extension must exist before PHP starts, so there
is no runtime `-d` flag that substitutes for it. This is the one thing that cannot
be a bind mount, and it is why this package exists at all rather than the loop
being a copy-paste.

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
composer test           # 6 tests
```

The Docker image needs the workspace root as context, because the framework and
auth are Composer path packages:

```bash
docker compose -f frankenphp/docker/compose.yml up -d --build
```

## Things that will waste your time

- **`$var` in the compose `command` block is Compose's**, not the shell's. Compose
  interpolates it before `sh` sees it, so `$ext` arrives as empty and the error is
  about a missing dynamic library. Escape it: `$$ext`. Same trap in any `command:`
  that is a shell script.
- **`APP_RUNTIME` does not exist here.** It was a branch in the app's front
  controller, which is why that file no longer has a branch.
- **Worker mode is not the default.** `app/docker-compose.yml` is nginx + php-fpm
  with no image build, and it is what the README and the root guidance describe.
  Do not "restore" FrankenPHP as the app's runtime.
- **Uninstalling this package must be a deletion, not an edit.** It is optional by
  construction. Anything that makes an application depend on it to run has broken
  the premise.

## The boundary with `principle`

Same as every module: **name a contract, not an internal.** The framework keeps
`Module` and `MigrationSource`; this package implements `Module` and calls
`Application::respond()`. If a change here seems to need a new framework method,
the question is whether the framework can answer it *without knowing what a worker
is* — usually it can, and usually the honest answer is a narrower contract.

`dev/tools/module-contract` checks that this package is a `library` requiring
`empira-principle` and not the application. Run it from the workspace root.

## Then

- [`principle/docs/workers.md`](../principle/docs/workers.md) — why workers leak,
  and the rules for your own code.
- [`../auth/agents`](../auth) — the other module, and the worked example for
  everything a module is.