# AGENTS.md

Guidance for `debugtools/` — `iam-adty/empira-debug-tools`, request profiling for
an Empira application, backed by [Clockwork](https://github.com/itsgoingd/clockwork).

Read [`workspace.md`](workspace.md) first; it describes how the five repositories
fit together. This file covers only what is specific to this one.

## What this is

A module. It records requests and serves an interface for looking at them, and
the framework has no idea it exists — no classes of its are named in
`principle/src`, no configuration of its is required, and an application that
installs it and switches it off behaves exactly as it did before.

Two contracts, both from `principle`:

```php
final class DebugToolsModule implements Module, CommandProvider
```

`Module::register()` publishes the profiler, the middleware and the routes.
`CommandProvider::commands()` contributes `debug:show` and `debug:clear`; the
framework checks those names against its own, so a collision here fails with a
sensible message rather than whichever command happened to win.

## Commands

```bash
composer test                      # the whole suite
vendor/bin/phpunit --filter testItRecordsARequest
```

Host PHP, SQLite, no services, no container. As with the other modules, the
framework is linked through a Composer path repository:

```bash
composer config repositories.principle path ../principle
composer update iam-adty/empira-principle
```

An edit under `principle/src` is live immediately.

## Wiring it

```php
use Empira\DebugTools\DebugToolsModule;

$app->module(new DebugToolsModule(__DIR__ . '/..'));
```

The base path decides where recorded requests are written: `$basePath/storage/clockwork`.
It defaults to the working directory, which is usually right.

```php
new DebugToolsModule(__DIR__ . '/..', ['enabled' => true]);    // force on
new DebugToolsModule(__DIR__ . '/..', ['storage_path' => '/tmp/cw']);
```

## Conventions

- **Edit source with the Edit tool**, not `sed -i` or a rewriting heredoc. Shell
  is for reading: `grep`, `find`, `git diff`, `php -l`. Several edits in this
  package's history were silently no-ops when written that way, and the suite
  caught them only because it was testing behaviour rather than the shape of the
  code.
- `declare(strict_types=1);` first in every PHP file.
- `final class`; constructor property promotion; `private readonly` where the
  value is fixed.
- Explanation belongs in docblocks, not inline comments.
- Tests: `final class XTest extends TestCase`, names that read as behaviour,
  `assertSame` over `assertEquals`. Every test drives `$app->handle()` rather than
  calling the data source directly — the interesting failures in a module like
  this are in the wiring, and none of those are visible from the inside.
- No emojis.

## Known traps

**`Clockwork::resolveRequest()` writes into whatever request object is already
there.** It does not create one. A single shared `Clockwork` instance — which is
what `$app->container()->get(Profiler::class)` gives — will otherwise store the
second request of a process under the first request's id, carrying its duration
and accumulating its timeline events. Under a worker that is one profile growing
for the life of the process, presented as though each page load were its own.
`Profiler::record()` installs a fresh `Clockwork\Request\Request` before resolving
for exactly this reason, and that line is load-bearing.

**`addDataSource()` appends without checking for duplicates.** Calling it per
request leaves the list one entry longer each time — invisible for a single
request, unbounded under a worker. The source is registered once, in the
`Profiler` constructor, and reused.

**`$_ENV` only — never `getenv()`.** This module read `getenv()` as a second
source for `APP_ENV`, and it was the only `getenv()` in the workspace. The
application strips its inherited configuration before reading `.env`
(`App::CONFIG_KEYS`), so anything arriving through the process environment is a
value the application never saw. A profiler deciding whether to write every
request to disk should decide on the application's answer, not a second one
gathered behind its back.

**There is one data source, and it is shared.** `EmpiraDataSource` looks
per-request, since it holds the request in hand, and binding a fresh one per
request in the container is the obvious thing to do — but the middleware writes
to it while Clockwork reads from the one registered on the instance. Two objects
means nothing is recorded at all. It is cleared and re-primed at both ends of
every request instead.

**Clockwork's file storage `fopen()`s its `index` unsuppressed, on read.** It
creates that file inside a *protected* method it calls only when storing. So
`latest()` on a fresh installation answers with PHP warnings and a false rather
than with an empty list — which is what `debug:show` and the interface ask,
precisely when nobody has debugged anything yet. `Profiler::storage()`
establishes the directory, the empty index and the `.gitignore` before handing it
out. The `.gitignore` is the easy one to miss: without it every recorded
request is a file the next `git add` commits.

**`storage()` answers with an object, or `false`.** Not an array, and not null.
`latest()` and `find()` return a `Clockwork\Request\Request`, which is **not**
`JsonSerializable` — `toArray()` is the shape to read, and handing the object
straight to `json_encode` produces something different from what Clockwork's own
interface expects.

**Deleting every file in the storage directory breaks it.** The `index` is
append-only and is opened on every read; removing it alongside the request files
leaves a storage that answers every later question with warnings and a false.
`debug:clear` truncates the index and removes only `*.json` and `*.json.gz`.

**A route catch-all will happily swallow the API.** `/app/{path:.*}` matches
everything after it, so the API routes are registered *before* it. Empira
matches in registration order.

**Exceptions thrown by handlers arrive at this middleware, and leave unchanged.**
The application catches them *outside* the pipeline. Recording the failure and
re-throwing is the whole contract: a profiler that skips exactly the requests you
wanted to debug is worse than no profiler. No headers are added on that path —
there is no response to add them to yet, and the application's error renderer
runs outside this pipeline and does not know this module exists.

**`Clockwork\Request\Request::$time` is set when the object is constructed, and
that is not when the request began.** `Profiler::record()` builds the object after
the handler has already run, so leaving `responseDuration` to Clockwork's
`getResponseDuration()` measures the recording rather than the request — a
five-millisecond handler is reported as taking the time it takes to serialise
itself. `EmpiraDataSource` sets `responseDuration` explicitly, from the clock
`begin()` started. It is **already in milliseconds**; converting again reports a
thousandfold. `tests/DebugToolsTest.php` sleeps 2ms in the handler on purpose: an
instant closure reports 0.0ms whether this is right or wrong, so a test without
the sleep passes against the bug.

**The bar's numbers travel in the page, not in a cookie.** `Response::cookie()`
*replaces* the `Set-Cookie` header rather than appending, so a bar setting a
cookie from the outermost middleware would silently overwrite whatever cookie the
application set while dispatching — and only on pages being debugged, which is
the worst time to discover it. A `script type="application/json"` island costs one
element and cannot collide.

**An absent `Content-Type` means HTML, here.** The framework sets none: both a
layout-rendered page and a handler returning a string come back with the header
unset. Requiring `text/html` would mean the bar never appeared on the pages it
exists for. It is safe only because the injection separately requires a `</body>`
— JSON has none — so the two checks together are stricter than either.

**`Response::headers()` is checked with `strcasecmp`, not an array key.** Header
names arrive as they were written, and `Content-Type` and `content-type` are the
same header.

## The bar

`resources/toolbar.js` and `resources/toolbar.css`, served by two routes that
**take no parameter**. That is deliberate: `DebugController::asset()` has to
defend against a path because it is handed one; these two have nothing to defend,
and adding a `{file}` parameter would be a way to have a traversal bug rather than
a way to avoid one.

Served as files rather than inlined so that an application sending a strict
`Content-Security-Policy` does not have to permit inline scripts in order to
debug. The data island is escaped with `JSON_HEX_TAG` for the same reason: a
route name containing `<` would otherwise close the element it lives in.

Injected before `</body>`, and skipped for JSON, for htmx requests
(`Request::isHtmx()` — a swap replaces one region and has no body tag), and for
fragments with no `</body>` at all.

Clockwork's own toolbar is a **separate npm or CDN package**
(`clockwork-browser`), not part of the Composer package — upstream injects
nothing into your HTML by design. This module does not pull it in.

## Definition of done

- `composer test` passes, and the suite *finishes* — `tools/test` checks for the
  summary line, because an `exit` anywhere truncates the run and still reports
  success.
- Every new behaviour is covered by a test that fails without the change. Verify
  by mutating the source and watching it go red.
- No new public API in `principle`, and no `empira-debug-tools` class named in
  `principle/src`.
- `dev/tools/module-contract` and `dev/tools/check-links` pass.
- README's install snippet works as written — `dev/tools/check-readme-installs`
  checks that, and it is the only check that exercises Composer's resolver
  against the real thing.