---
name: verify
description: Use before claiming a change works, before committing, or when asked to verify, check, confirm or test the framework. Runs lint, the PHPUnit suite, three documentation gates, and a live HTTP smoke test of the skeleton on the host, including htmx fragment rendering and that nothing outside public/ is served.
---

# Verifying the framework

> **Where this runs.** This skill lives in `dev/`, not in `principle/`, and the
> paths in it are written as if you are working in the **framework package**. Run
> commands with `principle/` as the working directory (`cd principle && …`), and
> read files as `principle/<path>` from the workspace root. The skill used to sit
> in `principle/.opencode/`, where a bare `tests/…` or `docs/…` just worked, and
> the mistake is easy to make: every path below now needs the prefix.

There is **no CI**. The test suite is the only gate, so run it before saying
anything works.

## House rules

**Edit source with the Edit tool.** Not `sed -i`, not a `python3 -c`, not a
heredoc that rewrites a file. Those have no review step, they fail silently on
a pattern that does not match, and they leave a half-applied file when they do.
Shell is for *reading*: `grep`, `find`, `git diff`, `php -l`.

**Put scratch in the workspace `tmp/`.** `tmp/` sits above both packages and is
mounted into every container, so a fixture written there is visible from the host
and from inside the runner. `/tmp` is out — it is outside the working directory,
which means an `external_directory` prompt on every use, and a failed run leaves
its evidence somewhere you will not think to look.

**Never rebuild to test a code change.** Nothing is built here. The suite runs on
the host PHP against SQLite, and an edit under `src/` is an edit in the next run —
there is no image, no mount and no step between the two. The only thing that
needs re-running is a dependency install; see [Rebuilding](#rebuilding).

## The checks

### 1. Lint

```bash
cd principle
find src tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

Every line must read `No syntax errors detected`. Include `bin/empira` — it is
PHP without the extension and has been missed by `find -name '*.php'` before.

### 1a. Documentation gates

Run these before the suite. None needs anything running, they finish in seconds,
and they catch the failures a green suite cannot — a test suite says nothing about whether
a document still describes the code.

```bash
dev/tools/module-contract              # every package shaped like its role
dev/tools/check-links                  # links, anchors, and named APIs all exist
dev/tools/check-readme-installs        # every README's install snippet works as written
```

`check-links` is the one to reach for after editing documentation. It is strict
on purpose: inside a published package a relative link that escapes that package
is a failure, because it resolves here — this workspace holds four repositories
side by side — and is a 404 for anyone holding one. A plain existence check
passes that class forever.

It also checks the **classes and methods the prose names**, which is what caught
three defects that read perfectly and were gone. In a fenced block, because
`check-links` would otherwise report each of these as missing — which is exactly
what they are, and why they are written here:

```
App::bootLock()                      never existed; it advised putting migrations in boot
Empira\Data\Connection               deleted when src/Data/ went to Doctrine
Application::errorResponse()         exists, and is private
```

Public and protected members both count, because documenting a method you are
meant to override is the documented path rather than an error. Sentences
describing history are skipped, so the framework's deleted `Grammar` layer stays
nameable without the tool asking you to erase the reason it was deleted.

`check-readme-installs` costs ~3 minutes cold and ~0.04s warm. Composer resolves a
`https://github.com/…` VCS repository through the **GitHub API** rather than
through git, and unauthenticated that is sixty requests an hour — enough for this
one script to hit the ceiling on a run where nothing had changed. So its verdicts
are cached under the commit **and the snippet text**, and `dev/.cache/` is
gitignored. An unchanged rerun makes no network call; edit the snippet or move a
commit and it re-runs for that package alone. `--refresh` forces all three.

If it ever does fail on the limit, it says so in those words — Composer's own
message for it is an authentication error, which sends you hunting for a token
you do not need.

### 2. Suite

```bash
cd principle && composer test
```

`483 tests, 1082 assertions` is the current baseline. **If your change adds or
removes tests, update that number in every document that quotes it** —
`dev/agents/principle.md`, `dev/planning/CHECKLIST.md`, `principle/ARCHITECTURE.md`, `principle/docs/testing.md`.

Faster loops — no rebuild, the source is mounted:

```bash
cd principle
vendor/bin/phpunit tests/RoutingTest.php
vendor/bin/phpunit --filter=testACycle
composer test -- --filter=testACycle
```

### 3. The other two suites

Both are plain PHPUnit on SQLite. The auth module is a
separate package with its own gate, and it is the one that proves the module
contract end to end.

```bash
cd auth && composer test          # 37 tests, 71 assertions
cd auth && composer validate      # must be clean
cd app  && composer validate      # must be clean — app/ has no suite of its own
dev/tools/app-test check          # 17 tests, 31 assertions — the generated app-test/
```

A change to `principle/src/Security/` or to the module contract is not finished
until `auth` passes, and `auth` cannot see an uncommitted change in `principle`
unless you have re-run `composer update iam-adty/empira-principle` there.

### 4. Live smoke, on the host

Start the application, curl it, stop it. No container and no server.

**Run from the workspace root**, so `tmp/` is inside the working directory and
the relative paths below mean what they say.

```bash
mkdir -p tmp
OUT=tmp/verify
rm -f "$OUT".*

(cd app && composer serve > "../$OUT".serve.log 2>&1 &)
sleep 2                                  # `php -S` needs a moment to bind

B=http://localhost:8000

curl -s -o "$OUT".home.html -w 'home          %{http_code}\n' "$B/"
curl -s -o /dev/null -w 'unknown url   %{http_code}\n' "$B/nope"
curl -s -o /dev/null -w 'src reachable %{http_code}\n' "$B/src/App.php"
curl -s -o /dev/null -w 'dotenv        %{http_code}\n' "$B/.env"

# an htmx request must get the region, not the document
TARGET=$(grep -oE 'id="region-[a-z]+"' "$OUT".home.html | head -1 | cut -d'"' -f2)
curl -s -o "$OUT".frag.html -H 'HX-Request: true' -H "HX-Target: $TARGET" "$B/"
grep -c '<html' "$OUT".frag.html          # must be 0
```

Expected: `200`, `404`, `404`, `404`, and `0`.

Three things that are easy to get wrong here:

- **`.env` is a `404`, not a `403`.** `php -S` has no dotfile rule, so the request
  reaches the front controller. It is still not served — `.env` sits outside
  `public/` — but any document quoting `403` was written against nginx.
- **`src/App.php` is a `404`** because only `public/` is a document root. If that
  ever answers `200`, the front controller is serving source.
- **`composer serve` binds `0.0.0.0`**, so it is answering on this machine's
  network address as well as loopback, serving the placeholder secret. That is
  fine on a private network and not fine on a shared one; `--host=127.0.0.1` if in
  doubt.

Stop it when you are done:

```bash
lsof -ti:8000 | xargs -r kill
rm -f tmp/verify.*
```

### 5. What this no longer covers

The step that used to sit here ran against a demo application: a sign-in form, a
dashboard, a forged component token, a missing CSRF header, and ten rounds of
session isolation across workers. **`app/` has one route.** There is no `/login`,
no `/dashboard` and no component to forge a token for, so none of that could be
curled — before this pass it also needed a container that no longer exists.

The coverage moved rather than vanished:

| What | Where it is now |
|---|---|
| CSRF on component actions, forged tokens | `principle/tests/ActionTest.php`, `TokenCodecTest.php` |
| session lifecycle, the guard, login | `auth/tests` |
| per-request resets in a long-lived process | `principle/tests/WorkerTest.php` |

What genuinely **cannot** be checked in this workspace is worker mode: it needs a
FrankenPHP binary, and nothing here runs one. That is recorded as unrun, by
decision, in CHECKLIST §5 — not as an oversight to fix quietly.

## Rebuilding

Nothing, because there is nothing to build. The suite runs on the host PHP, the
application is served by `php -S`, and this workspace ships no image, no compose
file and no entrypoint.

The only thing that needs re-running is a dependency install:

- a `composer.json` in either package — dependencies or an autoload path
- the first run on a fresh clone

After that a PHP edit is an edit in the next run. If you find yourself reaching
for a build step, you are looking for a step this workspace does not have.

## Prove the test can fail

The strongest check available, and it takes ten seconds:

```bash
cd principle
git stash
vendor/bin/phpunit tests/YourTest.php     # must FAIL
git stash pop
vendor/bin/phpunit tests/YourTest.php     # must PASS
```

`git stash` asks for permission, deliberately: it discards work, and a stray one
should never be a keystroke away. `git stash pop` has failed before.

A test that has never been seen red might not be testing anything.

## Watch for vacuous assertions

```php
// WRONG — passes on any page containing "htmx"
$this->assertStringContainsString('htmx', $body);

// RIGHT — can only pass if the behaviour worked
$this->assertStringContainsString('<li>multitail</li>', $body);
```

This exact mistake shipped: it matched `class="htmx-indicator"` and passed
regardless of what the search returned. **Ask what the opposite behaviour would
produce.**

## Before committing

```bash
dev/tools/module-contract
dev/tools/check-links
dev/tools/check-readme-installs
cd principle && composer test
cd auth && composer test
dev/tools/app-test check
cd app && composer validate
find src tests -name '*.php' -print0 | xargs -0 -n1 php -l
ls -A ../tmp          # nothing but .boot.lock
git status            # nothing unexpected, no vendor
```

If you touched `app/`, `dev/tools/app-test check` is its suite — `app/` itself
ships none, by decision, and that check builds the scratch application from the
skeleton.

## Reference

- `principle/docs/testing.md` — conventions and fixtures
- `principle/docs/workers.md` — one boot, many requests, and what is reset between them
- `CONTRIBUTING.md` — the full pre-commit checklist
- `AGENTS.md` — known traps
