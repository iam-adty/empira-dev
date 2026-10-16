# AGENTS.md

> **Status: describes shipped code.** Current behaviour, verified against `47
> tests, 130 assertions`.

Guidance for `debugtools/` — `iam-adty/empira-debug-tools`, request profiling for
an Empira application, backed by [PHP DebugBar](https://php-debugbar.com/).

Read [`workspace.md`](workspace.md) first; it describes how the five repositories
fit together. This file covers only what is specific to this one.

## What this is

A module. It records requests, injects a toolbar into full HTML responses, and
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

## Classes

| Class | Is | Because |
|---|---|---|
| `Debug` | the API a developer calls | no `Application` accessor exists to resolve one from |
| `Marks` | the buffer | DebugBar's collector cannot update an open measure or read one back |
| `Span` | the handle | timing what a closure cannot wrap |
| `Profiler` | the owner | one DebugBar, one storage directory, one set of ids |
| `EmpiraCollector` | what the request was | DebugBar reads the superglobals, not an Empira `Request` |
| `EmpiraDebugBar` | the metadata override | as above, and it is a one-method subclass |
| `DebugBarController` | the two endpoints | the toolbar is an iframe built from what these return |
| `DebugBarMiddleware` | recording and injection | wraps the whole request in both directions |

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

The base path decides where recorded requests are written: `$basePath/storage/debugbar`.
It defaults to the working directory, which is usually right.

```php
new DebugToolsModule(__DIR__ . '/..', ['enabled' => true]);    // force on
new DebugToolsModule(__DIR__ . '/..', ['storage_path' => '/tmp/dbg']);
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
  calling the collector directly — the interesting failures in a module like this
  are in the wiring, and none of those are visible from the inside.
- No emojis.

## Known traps

**`FileStorage::find()` orders by request id, and an id is a millisecond
timestamp.** The generator's own comment says "lexical sort = time sort", and for
requests seconds apart that is true. Two requests handled in the same millisecond
share that prefix and are ordered by the random bytes after it, which have
nothing to do with which happened first. `debug:show` reporting the *previous*
request is what that looks like, and two requests in a test are microseconds
apart, so it is not hypothetical. `ShowCommand` and the test helpers pick the
maximum `__meta['utime']` instead — `microtime(true)`, and a resolution nothing
in this path can exceed. **Do not "simplify" that back to `find()[0]`.**

**`FileStorage::find()` throws when its directory does not exist.** It hands the
directory to `FilesystemIterator`. Nobody has debugged anything yet is exactly
when `debug:show`, the toolbar and `debug:clear` all ask, so
a private setup step on `Profiler` establishes the directory before handing the
storage out. The `.gitignore` written alongside it is the easy part to miss:
without one every recorded request is a file the next `git add` commits.

**DebugBar's `collectMetaData()` reads `$_SERVER`.** It knows nothing about
Empira, and in a test the `Request` is constructed directly and never touches a
superglobal — so the metadata would describe the test runner.
`EmpiraDebugBar` overrides the one method and merges the empira values over the
parent's. Everything else it does is what is wanted.

**`StandardDebugBar` registers `RequestDataCollector`, and it must not be used
as-is.** That collector harvests `$_GET`, `$_POST` and `$_COOKIE` from the
superglobals **unconditionally**, on every profiled request — a session cookie
and a password field, written to disk and served back over HTTP by a tool that is
on by default in development. The collectors are therefore assembled by hand in
`Profiler`: `PhpInfoCollector`, `MessagesCollector`, `TimeDataCollector`,
`MemoryCollector`, `ExceptionsCollector`, and `EmpiraCollector`.
`StorageHistoryTest::testTheRequestCollectorsAreNotDebugBarsOwn()` asserts the
`request` key is absent and that a password and a cookie are nowhere in the
serialised profile.

**Upstream handlers echo and set headers.** `OpenHandler::handle()` and
`AssetHandler::handle()` both default to `$echo = true, $sendHeader = true`,
because they were written to be the last thing in a front controller that has
given up control of the response. Empira has not. Both are called with both
`false`, and the returned string is wrapped in a `Response` — otherwise two
writers race for one response.

**Handler arguments are autowired, so they must be resolvable.** A controller
method taking `array $query` fails with *"Cannot resolve $query of a callable"* —
there is no binding for it. `Empira\Http\Request` is bound as an instance per
request, so take `Request` and read `$request->query`.

**`MessagesCollector` names the level `label`, not `level`.** `addMessage()`
stores `'label' => $level`. A test asserting on `level` finds `null` and reads as
a logging failure.

**`TimeDataCollector::addMeasure()` defaults a missing end to the start.**
`Marks` leaves a span open until someone closes it, so an unfinished span arrives
as a zero-duration point. That is deliberate — stretching it to the end of the
request would claim work took the whole request when the truth is that it was
never measured to a finish.

**Repeated-measure merging is off by default. Keep it off.** `addMeasure()`
collapses same-label measures with identical params into one entry with a
`values[]` array when `mergeRepeatedMeasures()` has been called. Three
`Debug::mark('row')` calls would then become one measure and two payloads would
go nowhere, which is the opposite of what a mark in a loop is for.

**`$_ENV` only — never `getenv()`.** This module read `getenv()` as a second
source for `APP_ENV`, and it was the only `getenv()` in the workspace. The
application strips its inherited configuration before reading `.env`
(`App::CONFIG_KEYS`), so anything arriving through the process environment is a
value the application never saw. A profiler deciding whether to write every
request to disk should decide on the application's answer, not a second one
gathered behind its back.

**There is one collector, and it is shared.** `EmpiraCollector` looks per-request,
since it holds the request in hand, and binding a fresh one per request in the
container is the obvious thing to do — but the middleware writes to it while
DebugBar reads the one registered on the instance. Two objects means nothing is
recorded at all. It is cleared and re-primed at both ends of every request
instead.

**Exceptions thrown by handlers arrive at this middleware, and leave unchanged.**
The application catches them *outside* the pipeline. Recording the failure and
re-throwing is the whole contract: a profiler that skips exactly the requests you
wanted to debug is worse than no profiler. Nothing is injected on that path —
there is no response to inject into, and the application's error renderer runs
outside this pipeline and does not know this module exists.

**`Response::headers()` is checked with `strcasecmp`, not an array key.** Header
names arrive as they were written, and `Content-Type` and `content-type` are the
same header.

## Injection, and why it is bounded

This module rewrites response bodies. That is a change of contract from the
package's previous profiler, which was headless and left responses byte-identical,
so the boundary is drawn narrowly and every part of it is load-bearing.

the injection check in `DebugBarMiddleware` requires all of:

- **no `HX-Request` header** — a swap is answered with a fragment, and a toolbar
  appended to one would land inside the element being replaced, survive the swap
  and be duplicated by the next. This is the single reason the "injects nothing"
  claim could be dropped without consequence.
- **no vetoing content type** — a JSON body would no longer parse.
- **a `</body>` tag** — absent from a partial, and absent from a streamed file
  because `Response::body()` is `''` for those.

**An absent `Content-Type` means HTML here, deliberately.** `Response::html()`
sets no content type at all, so the check cannot *require* `text/html`; the
content type is only allowed to veto, and `</body>` is what establishes that a
response is a whole page. Requiring `text/html` would mean the bar never appeared
on the commonest HTML response there is.

Each of those three was checked by removing it and watching the suite go red. A
test that passes whether or not a rule exists is not a test of the rule — see
`DebugToolsTest::testNothingIsInjectedIntoAnHtmxSwap()` and its two siblings.

## The two endpoints

```php
GET /__debugbar/assets?type=js|css
GET /__debugbar/openhandler?op=find|get|clear
```

Both `csrf: false`. Neither is registered when the profiler is off.
`BootContractTest` in the harness asserts both patterns exactly — a prefix
substring would pass for either route.

`op=get` for an unknown id answers **200 with `[]`**. That is DebugBar's wire
format and the toolbar's JavaScript is written against it, so it is left alone
rather than "corrected" to a 404 that would break the client being served.

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
- **Run the suite more than once.** This package has a history of ordering that is
  correct at one-millisecond resolution and wrong in the gaps; a suite that passes
  twice out of three runs is not green.
- Every new behaviour is covered by a test that fails without the change. Verify
  by mutating the source and watching it go red — and check the mutation actually
  applied, because a pattern that matched nothing leaves a green suite behind.
- No new public API in `principle`, and no `empira-debug-tools` class named in
  `principle/src`.
- `dev/tools/module-contract` and `dev/tools/check-links` pass.
- README's install snippet works as written — `dev/tools/check-readme-installs`
  checks that, and it is the only check that exercises Composer's resolver
  against the real thing.