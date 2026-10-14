# AGENTS.md

> **Status: describes shipped code.** Current behaviour, verified against `38
> tests, 119 assertions`.

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

Four classes make up the developer-facing surface, and they are separate on
purpose:

| Class | Is | Because |
|---|---|---|
| `Debug` | the API a developer calls | no `Application` accessor exists to resolve one from |
| `Marks` | the buffer | the Clockwork request object does not exist while a handler runs |
| `Span` | the handle | timing what a closure cannot wrap |
| `Profiler` | the owner | one Clockwork, one storage directory, one set of ids |

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

## The header contract

This package's whole integration with a Clockwork client is three headers, and
`X-Clockwork-Path` is the one that is easy to get wrong:

```php
->withHeader('X-Clockwork-Path', DebugController::API)   // /_clockwork/api
```

A client — the Chrome extension, or Clockwork's own bundle — parses
`X-Clockwork-Id`, `X-Clockwork-Path` and `X-Clockwork-Version` for
`{id, path, version}` and then requests **`{path}/{id}`**. Clockwork's own value
for that path is `/__clockwork/`, and its server answers `/__clockwork/{id}`.

So the header is the **API base**, not the prefix the interface is mounted at.
Sending `DebugController::PATH` points every client at `/_clockwork/{id}`, a
route that does not exist, and the application looks perfectly healthy while
every client 404s. It shipped that way once.

`StorageHistoryTest::testAClientCanGetItsDataFromTheHeadersAlone()` performs the
journey from the headers alone. It is the test that would catch a regression, and
it is the reason to not "simplify" that header back to the prefix.

## Clockwork's timeline has three ways to lose your data

Found by reading `Clockwork/Request/Timeline/Event.php`, not by testing. Each
one fails silently — an event that arrives with no payload and a duration of zero
looks exactly like an event that had nothing to report.

**`Event` reads exactly five keys** — `name`, `start`, `end`, `color`, `data` —
and nothing else. A payload passed at the top level is none of those five and is
**discarded**. Everything this module attaches to an event is nested under
`'data' =>`. There is no way to have it quietly not work.

**`Timeline::event()` finds-or-creates by name.** `if ($event = $this->find($name)) return $event;`
Three `Debug::mark('row')` calls produce **one** event, and the other two
payloads go nowhere. This module's own lifecycle events use `event()` because
their names are unique per request; everything a developer marks uses
`create()`.

**`Event::finalize()` clamps to a window it is given:**

```php
if ($this->start < $start) $this->start = $start;
if ($this->end > $end) $this->end = $end;
```

`Clockwork::resolveRequest()` passes `$request->time`, and that object is built
in `Profiler::record()` — *after* the handler. So anything the application did
during the request starts before that and would be dragged forward onto the
instant recording began. `EmpiraDataSource::resolve()` overwrites
`$request->time` with the request's real start, which is the fix.
`DebugHelperTest::testMarksSurviveTheTimelineFinalizeClamp()` marks twice with a
2ms gap and asserts the gap survives, because a clamp to a single instant would
leave them indistinguishable.

## Storage answers with objects, `false`, and arrays

`Clockwork\Request\Request` is **not** `JsonSerializable`. `toArray()` is the
shape to serve, and all three of these have to be handled or a client gets a
subtly wrong document:

| Call | Returns |
|---|---|
| `latest()`, `find($id)` | one `Request`, or `false` when there is none |
| `all()`, `previous()`, `next()` | an **array** of `Request` |

Passing an array straight to `json_encode` encodes each object's public
properties — which omits every *computed* field, so a paged client would see no
duration and no query counts for records that have them. `DebugController`
maps both shapes, and
`StorageHistoryTest::testPagingEndpointsReturnTheSameShapeAsASingleRequest()`
compares the field lists so the two cannot drift apart again.

`find()` on an unknown id is a 404 here, not `null`: a 200 with a null body lets
a client cache "no such request" as though it were data.

## Nothing is injected into a response

This package ships no JavaScript, rewrites no response bodies, and serves no
HTML. There was an in-page toolbar and a wrapper page; both are gone, and
`DebugToolsTest::testAPageIsLeftByteForByteAlone()` asserts a page comes back
exactly as the handler wrote it.

That was a deliberate change, not an omission — upstream takes the same position,
and its toolbar is a separate npm or CDN component. The cost is worth recording
anyway: an early wrapper page embedded CSS and JS in a PHP heredoc using `{{`
and `}}`, which PHP does **not** escape. They went out literally, the CSS was
invalid, and the JS was a syntax error — a 300×150 iframe and a client with no
`metadataPath`, from one wrong brace. **PHP heredoc interpolates `$`, `{$…}`
and `${…}`. Braces are literal.**

## `Debug` is a static on purpose

No global `Application` accessor exists in the framework — `src/functions.php`
has only `hx()` — so a helper called three layers down has nothing to resolve
from. The static is safe because PHP is one request per process under a classic
runtime, and sequential under a worker, so a single slot set at the start of a
request and cleared in a `finally` cannot be contended.

`Debug::measure()` runs its callback **and returns its value** even when nothing
is recording. A debugging helper that changed what the application does when
switched off would be a bug in the tool, and that is the one behaviour here that
would be easy to "optimise" into a production incident.

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