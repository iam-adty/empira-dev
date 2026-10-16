# AGENTS.md

> **This file is [`dev/agents/workspace.md`](workspace.md).** The workspace root has
> its own `AGENTS.md`, holding the workspace rules; this one is the copy that
> used to be *the* file. Where the two differ, update the root one first — it is
> the one a session reads without being asked.

## What this directory is

A workspace holding **three independent Composer packages**. No build depends on
the others being in this directory — `app/` requires `principle/` and `auth/` the
way any application requires a framework. Each resolves its dependencies from
GitHub through Composer `vcs` repositories, so any one of them clones and
installs on its own.

```
.
├── principle/   git repo   iam-adty/empira-principle    Empira\             the framework
├── auth/        git repo   iam-adty/empira-auth         Empira\Auth\        authentication, as a module
├── frankenphp/  git repo   iam-adty/empira-frankenphp   Empira\FrankenPhp\  worker mode, as a module
├── debugtools/  git repo   iam-adty/empira-debug-tools  Empira\DebugTools\  profiling, as a module
├── app/         git repo   iam-adty/empira-app          App\                a bare skeleton
└── dev/         git repo   (not a package)                                   AI material, plans, skills
```

Nothing here is versioned at the workspace root — there is no repository there,
and adding one would tie the packages together in a way that makes them harder
to publish independently. `dev/` is a repository for the material that belongs to
none of them.

**The dependency runs outward.** `auth`, `frankenphp` and `debugtools` each
require `principle`; `app` requires whichever it installs. The framework requires
none of them, and boots, serves requests, maps entities and runs migrations
without them. `app-test/` sits outside all of it: generated, disposable, and never
pushed.

That direction is not an accident. Authentication used to live inside the
framework's spine — `Application` imported `Auth`, built one per request, and
`orm()` would not describe a configuration without mapping a `users` table. Every
application carried a users table whether it wanted one or not, and "make it
optional" was a rewrite rather than a move. A dependency that runs *inward*, from
core to feature, is one that cannot be cut.

## Start here

| You are working on | Read |
|---|---|
| the framework | [`principle.md`](principle.md) |
| authentication | [`auth.md`](auth.md) |
| worker mode | [`frankenphp.md`](frankenphp.md) |
| profiling | [`debugtools.md`](debugtools.md) |
| the application | [`app.md`](app.md) |
| plans and specs | [`../planning/CHECKLIST.md`](../planning/CHECKLIST.md), [`../planning/SPEC.md`](../planning/SPEC.md) |

Then the reference, which stays in the framework package because it ships to
consumers: [`principle/docs/`](../../principle/docs/).

**Where a session starts decides what it loads**, and every place works:

| Started in | Instructions | Skills |
|---|---|---|
| `principle/` | `principle.md` + the framework's docs | all four |
| `auth/` | `auth.md` + its README | all four |
| `frankenphp/` | `frankenphp.md` + its README | all four |
| `debugtools/` | `debugtools.md` + its README | all four |
| `app/` | `app.md` + its README | all four |
| `dev/` | all five agent files | all four |
| the workspace root | `AGENTS.md`, a pointer to this directory | none |

Each package's `opencode.json` is committed and names `../dev/…`, so nothing here
depends on a symlink or an environment variable. **The root is the one gap, and it
is deliberate**: there is no root `opencode.json`, because the root is covered by
no repository, and a third unversioned file drifting from this one would undo what
moving the material achieved.

So: read the package's own file when you are in a package, and this one for
anything that spans them.

## Rules for this workspace

**Every plan goes in the sidebar.** Any plan or to-do list must be written with
the todo tool, not only described in prose. Prose scrolls away, and a list that
never changes is worse than no list because it looks like progress tracking. Write
it when the plan exists, and update it as items complete, are dropped, or turn out
to be unnecessary.

**Git is per package, not here.** `principle/`, `auth/`, `frankenphp/`,
`debugtools/` and `app/` each have their own repository. Do not `git init` here,
and do not commit from this directory — there is no repository and adding one
would make the packages harder to publish independently. `dev/` is the exception,
and it was deliberate: the AI material spans every package and belongs to none of
them, so it is versioned separately rather than dropped.

**The root README is a note, not an artefact.** It is not versioned by any repo,
so it can drift from the code without anyone noticing. **The code is the source
of truth**; if it disagrees, it is wrong.

**Work in the package, not here.** Code changes belong in one package's `src/`,
committed to that package's own repository. AI material belongs in `dev/`.

**A module may not make the framework depend on it.** If a feature seems to need
something from core's spine, the question is whether core can answer it *without
knowing what the feature is about*. Usually it can, and the honest answer is a
narrower contract — `Security\Identity` and `Security\Guard` exist because
"who is asking" is answerable without owning what a user is.

**A module may not make the framework depend on it.** If a feature seems to need
something from core's spine, the question is whether core can answer it *without
knowing what the feature is about*. Usually it can, and the honest answer is a
narrower contract — `Security\Identity` and `Security\Guard` exist because
"who is asking" is answerable without owning what a user is.

## Running things

```bash
# the framework — host PHP, SQLite, no container
cd principle && composer test          # 528 tests

# the auth module — SQLite
cd auth && composer test               # 51 tests

# worker mode — SQLite
cd frankenphp && composer test         # 14 tests

# request profiling — SQLite
cd debugtools && composer test         # 47 tests

# the application — SQLite, and not in app/:
dev/tools/app-test check               # 26 tests, in the generated app-test/
```

And four checks, none of which need a server running — run these before claiming
something works, because they catch what a green suite cannot:

```bash
dev/tools/module-contract              # every package shaped like its role
dev/tools/check-links                  # links, anchors, and named APIs all exist
dev/tools/check-baselines              # every quoted test count is the count the suites report
dev/tools/check-readme-installs        # every README's install snippet works as written
```

`check-baselines` is the odd one out: it runs the four library suites, so it needs
`composer install` done in each of them, and it skips `app-test` rather than failing
when the scratch application has not been built.

Scratch files belong in the workspace `tmp/`, never `/tmp`. It sits above every
package, so one relative path — `dirname(__DIR__, 2) . '/tmp'` — finds it from any
of them. It should hold nothing at all: every test that creates a fixture there
removes it, and `ls -A tmp` is how you check that it did.

```bash
ls -A tmp
```

```bash
cd app && composer serve               # loopback, :8000
```

That is the whole runtime: the framework's console, handing over to `php -S`.
Nothing is built, nothing is started, nothing is waited on. It binds loopback,
so the page answers only on this machine — which is what keeps the placeholder
secret off the network. `--host=0.0.0.0` opens that deliberately, for when the
machine is a VM you reach from elsewhere; see [`app.md`](app.md).

Worker mode is optional and lives in its own package. It ships no image — a stock
PHP cannot become FrankenPHP at runtime, and whether one should be built is an
application's decision. Nothing in this workspace runs a worker by default, and
nothing in it has ever done so; see [`frankenphp.md`](frankenphp.md).

Dependencies resolve from GitHub, so a lone clone works:

```bash
cd app && composer install
```

There are no bind mounts and no image build, and nothing here depends on the
directories being beside each other any more. Each package installs its siblings
through Composer's `vcs` repositories, so `app` gets `principle` and `auth` from
GitHub rather than from the checkout next to it. A sibling edit is therefore not
live: `composer update iam-adty/empira-principle` is what pulls one in, and the
change has to be pushed first. That is the trade for not pretending a workspace
layout is a distribution channel.

Each package requires `dev-main`, so it tracks the **branch**, not a release. A
change to `principle/main` can therefore break `app`'s install. `app` is the one
package that commits its `composer.lock`, so its own build is pinned; the two
libraries are not.

**Do not tag to stage a change.** That is the instinct, and it is the expensive
one here: a tag cannot move, so every consumer needs a constraint bump and a
re-lock, and nothing outside this workspace depends on these packages anyway. A
change that should not land on consumers at once goes on a **branch** and is
merged when it is ready — `composer require iam-adty/empira-principle:dev-feature`
resolves a branch as well as `main`.

All three libraries share one branch alias, `0.1.x-dev`, so a consumer can write
`^0.1@dev` instead of only `dev-main`. `module-contract` checks they agree and
that nothing is tagged; both of those exist because the obvious alternative —
tagging `0.x` — makes Composer treat the version as **stable**, since it parses
`0.1.0` and `0.0.1` that way. The first release, when there is a reason for one,
should be `0.1.0-alpha.N`.

**A library is `dev-main`, so `app` runs whatever GitHub has, not what is beside
it.** After editing `principle/src`, `composer update iam-adty/empira-principle`
inside `app` — and the change has to be pushed first, because `app` installs from
GitHub rather than from the checkout. The symptom of forgetting is a change that
"did not work", not an error. `dev/tools/app-test` has the same property by
design, and `APP_REPO=../app` builds from the working tree instead when that is
what you want.

## The application is a skeleton on purpose

`app/` is three files of source. It was a demo — sign-in, registration, a
dashboard, rate limiting, eight components — and all of that is gone.

A skeleton somebody copies arrives with decisions already made by someone else: a
rate limiter you did not ask for, a session strategy you have to unpick, demo
credentials seeded on first boot. Each is a thing to *remove*, and removal is
slower than never having it.

**The trade is real:** the app no longer demonstrates what the framework can do.
Authentication is demonstrated by `auth/`'s 51 tests, components and routing by
the framework's 521. If you are looking for a worked example of a feature, look
there rather than in the app.

## Cross-repository status

Planned work is tracked in [`../planning/CHECKLIST.md`](../planning/CHECKLIST.md) and
[`../planning/SPEC.md`](../planning/SPEC.md), both in `dev/`. Nearly all of it is
framework-side, with the application following once the framework lands — which is
why those files live in a repository of their own rather than inside one of the
three, where they would look like one package's private plans.

## Skills

Use the skill tool to load a skill when a task matches its description.