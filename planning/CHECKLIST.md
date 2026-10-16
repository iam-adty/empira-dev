# Checklist

> **Status: live.** Checked items are struck, not deleted — the history is the
> point. If an item here disagrees with the code, the code wins and this file is
> a bug.

Framework: `iam-adty/empira-principle` · app: `iam-adty/empira-app`
Current: **521 framework tests / 1168 assertions**, **51 auth tests / 99 assertions**, **14 frankenphp tests / 24 assertions**, **45 debugtools tests / 125 assertions**, **26 app-test tests / 56 assertions** (in the generated `app-test/`, not in `app/`).

---

## Shipped

### Core component model
- ~~Fluent `Element` builder with the full `hx*()` surface~~
- ~~Signed component tokens (`TokenCodec`, HMAC over props)~~
- ~~Registry allow-list with short, rename-safe aliases~~
- ~~Stateless-by-default props riding the token; opt-in `StateStore`~~
- ~~CSRF for component actions, inherited via `hx-headers` on `<body>`~~

### Typed state — 2026-10-10
- ~~`#[Prop]` on typed properties; props hydrate before user code runs~~
- ~~Coercion to the declared type, falling back to the default~~
- ~~Object-typed props never instantiate a class named by a token~~
- ~~`private` / `static` / `readonly` props ignored~~

### Actions and verbs — 2026-10-10
- ~~`#[Action]` + `#[Get]/#[Post]/#[Put]/#[Patch]/#[Delete]`~~
- ~~The declared verb drives both the `hx-*` name and the router's method check~~
- ~~405 with an `Allow` header on a verb mismatch~~
- ~~`actions()` kept working; the two declaration styles union~~

### HTTP stack
- ~~Routing: closures, `[Class, 'method']`, groups, named routes, `url()->to()`~~
- ~~Middleware pipeline, onion-ordered, autowired~~
- ~~Session with flash messages~~
- ~~Request/Response: cookies, downloads, streaming, cache headers~~

### Views
- ~~`Layout` + `Region`; one route serves the document or a single region~~
- ~~Boosted links still receive a full document~~

### Support
- ~~Autowiring container with cycle detection~~
- ~~Validation: 15 rules, error bag, no coercion~~
- ~~Logging: PSR-3, with a Monolog implementation in the application~~ — was
  a hand-rolled `Logger`/`LogLevel`/`FileLogger`, removed 2026-10-12 in favour
  of `psr/log` so the logger an application already has is the one the framework
  writes to. The framework records an exception that ends a request, before
  re-throwing it; it did not record anything at all before.
- ~~Persistence: Doctrine ORM + DBAL + Migrations~~ — replaced a hand-written
  PDO query builder, dialect layer and migrator (1433 lines, gone). One reader
  for `DB_*`, a real version ledger, and `empira migrate`/`--status`/`--step=down`.
- ~~Config: `.env` parsing, dot-notation, typed getters~~
- ~~`TestClient` for application authors~~
- ~~CLI: `make:component`, `make:controller`, `route:list`, `serve`, `migrate`~~

### Packaging — 2026-10-11
- ~~Split into two Composer packages: `principle/` (library) and `app/` (project)~~
- ~~Namespace `Empira\` → `Empira\`~~
- ~~`app` links the library through `franzl/studio` (`dev-main`)~~
- ~~Two git repositories, one per package~~

### Fixes — 2026-10-11
- ~~`<style>` was missing around a layout's stylesheet~~
- ~~`Response::html()` silently discarded a second argument~~
- ~~Group middleware was attached but never executed~~
- ~~Session rotated its flash buckets on every access~~
- ~~`validate()` fired its event even on success~~
- ~~A session outlived the request that opened it, leaking between users in a worker~~
- ~~The context outlived its request, so a view between requests mutated a sent response~~
- ~~Middleware instances froze the first request's `Format` and `Auth`~~
- ~~A worker's statement log grew without bound~~

### Composition — 2026-10-11
- ~~`$app->components(__NAMESPACE__)` — reachable by namespace, not by a list~~
- ~~`Component::child()` returns the child; `children()` reports what was composed~~
- ~~A nested component needs no registration — the 404 trap is gone~~
- ~~`renderComponent()` is shorthand for `child()->markup()`~~

### Runtime entry points — 2026-10-11
- ~~`Application::respond()` — the worker's `send()`, without the exit~~
- ~~The gate cannot be passed by a run that did not finish~~
- ~~`failOnEmptyTestSuite` — a suite that ran nothing is a failure~~

### Aggregated styles — 2026-10-11
- ~~`aggregateStyles()` — one sheet for a composite's whole subtree~~
- ~~`StyleOrder` — where a composite's rules sit among its children's~~
- ~~Identical rulesheets once per page, not once per instance~~
- ~~`swapsIndependently()` — a child that keeps its own sheet and is not collected~~

### Long-running workers — 2026-10-11
- ~~`Session::beginRequest()` resets between requests; five tests fail without it~~
- ~~`Application::respond()` — a non-exiting send, so a worker survives more than one request~~
- ~~The context is cleared in a `finally`~~
- ~~The EntityManager's identity map is cleared per request~~ — was the
  connection's statement log, which went with the data layer; same trap, same
  place it is handled now
- ~~The shared-store rule: worker memory holds only re-derivable data~~

### Doctrine — 2026-10-12
The framework took its first runtime dependencies and gave up the
zero-dependency claim, which is no longer true and is not papered over. Doctrine
was chosen for the standardised parts; components, routing, views and the htmx
wiring are still the framework's own.

- ~~`psr/log` — the framework asks for PSR-3 and supplies no logger unless the
  application hands one over~~
- ~~`Config\DatabaseConfig` — one reader for `DB_*`; two readers with different
  defaults had `empira migrate` reporting success against a database that
  vanished on exit~~
- ~~`Console\Bootstrap` — an application registers its own commands; the console
  finds them by running `bootstrap/app.php`~~
- ~~`Persistence\EntityManagerFactory` — `orm()`, and a `clear()` beside
  `Session::beginRequest()`~~
- ~~`Auth\User` is an entity, `DoctrineUserProvider` reads it~~
- ~~`src/Data/` deleted — 1433 lines the ORM had already replaced~~
- ~~`Request::ip()` — the socket address, and proxy headers only when
  `Application::trustProxies()` says so; a log full of client-chosen addresses
  is worse than none, because it looks like evidence~~
- ~~An `onHttpError` view no longer discards the headers it was handed~~ — it
  chooses the body, not what the response means

### Operations — 2026-10-12
- ~~Migrations and seeding are deploy steps — `empira migrate`, `empira seed` —
  because a worker boots once per thread and a boot that migrates has every
  thread racing through the same migration~~
- ~~The application refuses to start on the placeholder `APP_SECRET` outside a
  local environment~~

### Modules — 2026-10-12
Authentication left the framework. It was the reason every application carried a
`users` table whether it wanted one or not, because the dependency ran *inward* —
core to feature — and a dependency that runs inward is one that cannot be cut.

- ~~`Security/{Identity,Guard,RequestUser,NonAmbientCredential}` — the read half,
  which core keeps because a layout asking "is anyone signed in" and CSRF asking
  "was this proven without a cookie" must both work in an application that
  installed no auth module~~
- ~~`Auth` is now only the write half — `login()`/`logout()`, plus a narrowing to
  the module's `User`. Everything that knows what a user *is* went with it~~
- ~~`Module` and `MigrationSource` — one method each, and opt-in for migrations
  because most modules have no schema to create~~
- ~~`orm()` maps no directory of its own. It used to include `src/Auth`, so an
  application could not have an ORM without getting a users table~~
- ~~`migrate` plans across modules in one sequence against one ledger, which needs
  `Bootstrap::boot()` to return the application and `bin/empira` to hand it over~~
- ~~`../auth` is its own package: `AuthModule`, `User`, both guards, the providers,
  `RequireAuth`, the two `users` migrations, and 540 lines of tests~~

---

## Next

In priority order. See [SPEC.md](SPEC.md) for the design behind each.

### Outstanding, decided but not started

Recorded here rather than left in a session transcript. Each is a decision that
has already been made — none needs deciding again, only doing.

- **`franzl/studio` in `app-test/`.** Approved — the profiler UI as a Composer
  dependency is worth trying. Needs a spike first: last release 0.16.0, October
  2022, so confirm it still runs against Clockwork 5.x before designing anything.
  `studio.json` shape is `{"version":2,"paths":[…]}`. It belongs in `app-test/`
  only — **`app/` stays a bare `composer create-project` skeleton.**
- **`dev/tools/check-console`.** A subprocess test for the console. The
  subprocess part is wanted; **browser tests were cancelled and are not to be
  added.** §17 records the gap this fills.
- **An `app-test` staleness guard.** Nothing fails when `app-test/` is older than
  the libraries it installs, and it is easy to read a stale suite as a passing
  one — this session hit exactly that: a 26/26 that meant nothing because
  `tools/app-test build` is not a subcommand, so the build silently no-opped and
  the suite ran against `b8cedbb5`. `reset` is the real verb.
- **SPEC §7, navigation as an SPA** — parked since early on, and deliberately
  unimplemented: `app/` ships a skeleton, and an SPA shell is not a skeleton. The
  draft is outside the repositories, in the session plan directory.

### 1. Attribute routing `[shipped]` — 2026-10-11
- ~~`Attributes/Route.php` — `TARGET_CLASS|TARGET_METHOD|IS_REPEATABLE`~~
- ~~`Routing/RouteRegistrar.php`, `RouteRegistry.php`, `RouteParams.php`~~
- ~~Specificity sort (priority → fewest params → most literal → longest → registration)~~
- ~~`Application::discover()` and `Application::registrar()`~~
- ~~`view()` merges route params; `mount()` injected by reflection~~
- ~~Auto-derived route names~~
- ~~CLI `route:list` boots the real app~~
- ~~Tests: discovery, ordering, repeatable attribute, mount injection, middleware, skips~~
- ~~Migrated `app/` to `#[Route]`; `app/routes.php` deleted~~

**Two bugs the tests caught, both worth remembering.** `commitDiscovered()`
assigned `usort()`'s return value — which is `true` — to the sorted array, so
nothing was ever sorted. And the registry skipped abstract classes but not other
non-instantiable ones, so a `#[Route]` on a class with a private constructor
registered a URL that could not be built.

### 2. JSON / API routes — 2026-10-11
- ~~`Request::accepts()` — real q-value parse; explicit mention beats wildcard~~
- ~~Format negotiated once in `handle()`, readable via `Application::format()`~~
- ~~One error envelope: `{"error": {status, message, errors?}}`~~
- ~~**Fixed:** `ValidationException` mapped to 422 — it used to rethrow and fatalise~~
- ~~`VerifyCsrfToken` format-aware~~
- ~~29 tests: accept matrix, envelope per status, HTML path byte-identical~~

htmx is protected by the rule that made it necessary: **htmx sends no `Accept`
header at all**, so a wildcard resolves to HTML. Verified against
`htmx.org@2.0.4`, whose intent signal is `overrideMimeType('text/html')`.

### 3. Documentation — 2026-10-11
- ~~`AGENTS.md`, `ARCHITECTURE.md`, `SPEC.md`, this file~~
- ~~`CONTRIBUTING.md`, `CHANGELOG.md`, `SECURITY.md`~~
- ~~`docs/` reference pages — 8, including `json-errors.md`~~
- ~~`.opencode/skills/` (4) + `opencode.json`~~

### 4. Operations — 2026-10-11
- ~~CI — removed; the suite runs locally through Docker (see `docs/testing.md`)~~
- ~~Decided: `.opencode/` and `tests/` are **export-ignore**d — the
  published package carries `src/`, `docs/`, `bin/` and the markdown docs. The
  skills point at this repository's own `tests/` paths, which do not exist in a
  consumer's `vendor/`; contributors get them from the git checkout~~
- ~~Push both repositories to a remote~~ — **done, and this line was wrong for
  weeks.** All five repositories are public on GitHub under `iam-adty`, pushed to
  `main`. `dev` became a repository of its own rather than living inside another
  package, which is what lets one set of agent guidance cover all five without
  being copied between them.

### 5. Classic runtime and worker mode — shipped 2026-10-12

The reference application no longer builds an image. It runs on stock
`nginx:alpine` and `php:8.4-fpm-alpine` with the workspace bind-mounted, so an
edit in a library's `src/` is live in the running container. Three stock services,
no `Dockerfile`, no rebuild. Verified: `GET /` → 200, unknown route → 404,
`.env` → 403, both auth migrations applied to an empty PostgreSQL, and **5/5 cold
starts from wiped volumes**.

Worker mode moved out of `public/index.php` into `iam-adty/empira-frankenphp`, an
optional module holding the loop and a base image. `public/index.php` is
classic-only and has no `APP_RUNTIME` branch. The module list moved to
`bootstrap/modules.php`, and `dev/tools/module-contract` checks every package is
shaped like its role.

Three things were measured rather than assumed, and are written down in the
compose file and the nginx config because each was found the hard way:

- **`pdo_pgsql` needs `-d` on the `php-fpm` binary, not a pool setting.** The
  extension loads when the SAPI starts, so `php_admin_value[extension]` is too
  late and silently ignored. The CLI is a separate SAPI and needs the flag too —
  without it the driver is present for every request and absent for the migration,
  which surfaces as a connection error mentioning nothing about extensions.
- **Compose interpolates `$var` in a `command:` block before the shell sees it.**
  Escape it as `$$var`. The symptom is a missing-dynamic-library error for a path
  that is right in the file.
- **nginx must not resolve `php` at startup.** It resolves a literal upstream while
  parsing config, and Docker registers a service's DNS alias after the dependent
  container starts — so the first `up` died with `host not found in upstream "php"`
  and the second worked. `depends_on` narrows the window and does not close it. A
  `resolver` plus a variable in `fastcgi_pass` moves the lookup to request time.

**Reading the worker package before pushing it found two bugs.** Both had been
committed, and both were invisible to a passing suite.

- **`worker.php` could not start for anyone who installed it.** It resolved its
  autoloader as `dirname(__DIR__, 2) . '/app/vendor/autoload.php'` — right for a
  sibling checkout, and `app/vendor/app/vendor/autoload.php` for the only layout
  anybody ships. The README told people to run exactly that path. Fixed by
  resolving against the working directory, which is the application root in every
  documented way of running the worker.
- **The `Dockerfile` built an application that does not install the package.** Its
  `CMD` named `/app/vendor/iam-adty/empira-frankenphp/worker.php`, which
  `composer install` never creates, because `app/` deliberately does not require
  this module. It also `COPY`-ed its own `composer.json` into a path `COPY app /app`
  overwrote two lines later.

The second is why "it has never been built" was worth writing down rather than
waving past: the reason it was never built is that it could not have worked.

**The image and its compose file were removed.** They were scaffolding for a
premise that turned out false, and an unbuilt image in a published package reads
as tested. What remains ships and runs: the loop, the entrypoint, the refusal, and
`bin/frankenphp-worker`. The reasoning that *some* image is unavoidable stays in
the README, because it is true — FrankenPHP cannot be added to a stock PHP the way
`pdo_pgsql` can — along with what a new one would have to get right.

**Unfinished, by decision:**

- ~~**No FrankenPHP process has ever served a request from this package.**~~ The
  loop, the entrypoint's path resolution and the module's refusal are covered by
  tests; none of it has been executed against a real worker. Correct by reading, not
  by running — and reading is what found the two bugs above. Worker mode is
  deliberately off radar until it is wanted properly.
- ~~**`empira-frankenphp` has no remote.**~~ **DONE.** The repository was created
  and the package pushed. All five repositories are public and in sync.
- ~~**Runtime parity was checked for the classic path only.**~~ `nginx + php-fpm`
  and the built-in server were compared and agree (`/` → 200, unknown → 404). The
  worker was never compared against either, and cannot be while it has never run.
- ~~**`dev/agents/app.md` and `dev/agents/workspace.md` still describe FrankenPHP
  as the application's runtime.**~~ **DONE.** Four stale lines in `app.md` and one
  in `workspace.md`. Corrected during the push rather than left for the next plan:
  shipping a doc that points at deleted files is actively misleading.

### 6. Versioning baseline — decided 2026-10-12

**Branch alias, no tags, not yet.** All three libraries declare

```json
"extra": { "branch-alias": { "dev-main": "0.1.x-dev" } }
```

Dependencies still require `dev-main`; it now resolves as
`0.1.9999999.9999999.9999999-dev` instead of the meaningless `dev-main`. The
project, `app`, declares no alias — a project is not versioned.

**Why an alias and not `0.x`.** Composer parses `0.1.0` and `0.0.1` as
**`stable`**. Verified against Composer 2.10.2's `VersionParser`, not assumed:

| Tag | Stability |
|---|---|
| `v1.0.0`, `v0.1.0`, `v0.0.1` | `stable` |
| `v0.1.0-alpha.1` | `alpha` |
| `dev-main` | `dev` |

So a `0.x` tag would mark a release by Composer's own rules, which is the one
thing this decision exists to prevent. Only `-alpha.N` / `-beta.N` / `-RC.N` and
`dev-*` are non-stable, which is why the alias ends in `-dev`.

**Why no tag.** Nothing outside this workspace consumes these packages. A tag
earns nothing today and costs a constraint bump plus a re-lock for every change,
because a tag cannot move. Revisit when there is a real consumer or a Packagist
release; **`0.1.0-alpha.N` is the shape to use then**, because `alpha` is
genuinely non-stable and `0.x` promises nothing about a 1.0 API.

**What this does not fix.** `app`'s lock had drifted five commits behind
`principle`. That was never caused by the absence of tags — `composer install`
honouring the lock is the lock doing its job, and `app/vendor` matched its lock
exactly. Regenerate with `composer update iam-adty/*` when the app should be
current; do not run it on a schedule chasing a number.

**Two ordering facts that cost time to find:**

- **Push the alias commits before regenerating the app's lock.** A branch alias is
  read from the *fetched* `composer.json`, so a lock recording an aliased version
  depends on the alias existing at the locked commit. Updating first locks a
  tagless commit, and the lock then disagrees with every manifest.
- **The alias is cosmetic, not a resolver change.** Nothing about which commit is
  selected differs; only the version string it is recorded under.

**What the alias does and does not do** — worth being exact, because the obvious
assumption is wrong:

| | Before | After |
|---|---|---|
| `composer show` | `dev-main` | `0.1.x-dev, dev-main` |
| Require by branch: `dev-main` | works | works |
| Require by version: `^0.1@dev` | **no such version** | works |
| `composer.lock` records | `dev-main` | **`dev-main` — unchanged** |

The lock keeps the literal branch name. A branch alias influences constraint
matching and what `composer show` lists; it does not rewrite the `version` field
Composer writes for a package installed from a VCS branch. That was expected to
change and did not, and it is the honest limit of what this buys: a consumer can
now express a *version* constraint instead of only a branch name, which is the
real payoff, and the lock stays as readable as it was.

`dev/tools/module-contract` checks that all three libraries declare an identical
alias and that the project declares none — a copy-paste drift to `0.2.x-dev` in one
manifest is otherwise invisible until something resolves against it.

### 7. Documentation truth — shipped 2026-10-12

Documentation that is confidently false is worse than documentation that is
missing, because a reader has no way to tell. Four classes of it were found and
fixed; the last one was only findable by running the instructions.

- **A changelog claiming the project had no remote.** "No git tag exists, no remote
  is configured, and the package is not on Packagist" — three claims, one true. All
  five repositories are public and pushed. Also corrected for the alias baseline:
  `0.x releases as a moving target` presumed `0.x` was the release line.
- **A historical snapshot that read as current.** "No git remote" also appears under
  *Known limitations — true and unfixed at this point*, where it **was** true. It
  was left alone; redacting a true historical record to make a document consistent
  is its own kind of lie. The heading now dates the snapshot and points at the
  checklist for current state.
- **A rationale that stopped being true.** The CI workflow "never ran because
  neither repository has a remote" — the fact stays, the reason is gone.
- **Security reporting maturity tied to version number.** `SECURITY.md` justified
  having no disclosure policy by being pre-1.0. It does not, and in a security
  document that link is worse than saying nothing.
- **Install instructions that could not run.** Every package 404s on Packagist
  (verified against the API), and three READMEs opened with a bare
  `composer require` that fails. Now they show the `repositories` block over
  https, so they work without an SSH key.

**Requiring the framework at the root is not ceremony**, and only running the
snippet revealed it. Composer's default `minimum-stability` is `stable`, so a
transitive dependency on a `dev-` branch is refused:

    - iam-adty/empira-auth dev-main requires iam-adty/empira-principle dev-main
      -> found iam-adty/empira-principle[dev-main, 0.1.x-dev (alias of dev-main)]
      but it does not match your minimum-stability.

Both module READMEs now require both packages at the root and quote that error, so
the cause is recognisable to whoever hits it. `"minimum-stability": "dev"` is the
one-line alternative and works; it is documented with the reason it is not the
recommendation — it also lets any other dependency's development branch through.

`dev/tools/check-readme-installs` runs every README's install snippet verbatim and
fails if any does not work. Two bugs in the first version of that script — it
double-wrapped the repositories block, and PHP encoded an empty `require` as `[]` —
were invisible until it was executed, which is the argument for the check existing.

### 8. Deferred, by decision
- ~~`Format::Auto` override on `#[Route]`~~ — **not adding it.** htmx's
  `overrideMimeType` means htmx traffic is permanently HTML, so a route-level
  force would be a rule with no correct use. `onException()` covers apps that
  want guaranteed JSON errors.

### 9. The skeleton ships no tests — 2026-10-13
- ~~`tests/`, `tools/test`, `phpunit.xml`, the phpunit `require-dev` and the
  `test`/`setup`/`seed` scripts deleted from `app/`~~ — it is what a consumer
  copies, so a suite committed there is a suite they inherit without asking. The
  manifest also contradicted its own suite: `composer setup` called an
  `empira seed` that did not exist, beside an `AppTest` asserting the application
  registered no commands at all. `composer setup` is the first thing anyone runs
  on a fresh clone, and it failed every time.
- ~~`app-test/` — the suite, generated by `dev/tools/app-test` from `app/`'s own
  GitHub clone plus `dev/app-test-harness/`~~ — disposable, not a repository, and
  never pushed. Building from the *clone* rather than the working tree is what
  makes it a check on `app/` being installable by someone who has only what is
  published. `APP_REPO=../app` overrides it to test an unpushed change.
- ~~The seeder moved with it~~ — `App\SeedCommand`, in the scratch app only. It
  lives in `src/` rather than `bootstrap/harness.php` because
  `Console\Bootstrap::boot()` `require`s that file once per console command, and
  a class declared at its scope is declared again on the second call and fatals.
  Measured, not assumed: 7 re-requires per suite run.
- ~~`dev/tools/module-contract` enforces it~~ — no tracked tests, no phpunit in
  `require-dev`, no `autoload-dev`, no `test`/`setup`/`seed` scripts, none in the
  committed lock. All five violations were introduced and confirmed caught.
- **One consequence, stated rather than hidden:** because the suite is generated
  from a clone, a change to `app/` is uncovered until it is pushed and
  `app-test/` rebuilt.

### 10. The console is framework core — 2026-10-13

`empira` was already core — five built-ins, a registered `bin`, project commands
reached through `bootstrap/app.php`. What it could not do was the thing a
framework is supposed to make easy: a package shipping its own command.

- ~~`Console\CommandProvider`~~ — opt-in, collected by `Application::module()`
  beside migrations, for the reason `MigrationSource` is: an interface every
  module must implement only to return `[]` teaches nothing.
- ~~`Application::discoverCommands()`~~ — a class under `app/Console/` declaring
  a name is a command, as in Laravel. The name comes from `Command::name()`, not
  the filename; commands are built by the container so one may ask for what it
  needs.
- ~~`ClassScanner`~~ — route and command discovery are the same question asked
  twice, so the scanner moved out of `RouteRegistry` rather than being copied.
- ~~`App::boot()` / `App::bootConsole()`~~ — one private `build()`, two named
  entry points. `boot()` served web through a `bool $csrf` whose only caller
  passing `false` was the console bootstrap.
- ~~**Discovery lives in `bootstrap/app.php`, not in `App::boot()`.**~~ Building
  an application knows nothing about what it exposes on a command line. The
  skeleton ships `src/Console/` with nothing in it — visible, and empty on
  purpose.
- ~~The scratch application's harness shrank~~ — a skeleton that discovers its
  own commands has no reason to ship a harness that registers them, so
  `bootstrap/harness.php` and a second copy of `bootstrap/app.php` were deleted.
- **Module migrations were deliberately left alone.** Copying them into the
  application's `migrations/` was considered and rejected: a copy must rewrite the
  namespace, can collide on a version string two sources could never collide on,
  and orphans a migration nobody can attribute when a module drops one. The
  existing mechanism — one planned sequence across every source, one version
  ledger — is correct and covered by `testTheModuleContributesItsMigrations`,
  `testTheMigrationNamespaceMatchesTheFilesThatDeclareIt` and the scratch app's
  `testTheModuleBroughtItsTableWithIt`.

### 11. SQLite only, and no containers — 2026-10-13

Removed by decision. Doctrine is untouched: DBAL opens the connection, ORM maps
and queries it, Migrations plans against it.

- ~~`PostgresTest` deleted~~ — five tests, the only ones in the suite that
  reached a database over a socket. **A green run now proves Doctrine works
  against SQLite and nothing more.** "SQLite forgives a great deal" is why they
  existed, and it is still true of what is left.
- ~~`DatabaseConfig` speaks SQLite only~~ — the mysql and pgsql mappings went,
  and with them the `host`, `port`, `username` and `password` parameters, which
  existed only to reach a server and would otherwise be published and ignored.
  `DB_DRIVER=pgsql` is refused **by name**: falling back would be worse, because
  SQLite would create an empty file and the application would report having no
  data.
- ~~`tools/test` runs on the host~~ — a daemon, an image and a container per run
  were being spent on 480 tests that open a file. The suite is 490 tests in 0.7s
  with nothing started. **The early-exit check is unchanged and re-proved**: bare
  `phpunit` returns 0 on a truncated run; `tools/test` returns 1 and says why.
- ~~Every container artifact deleted~~ — `principle/{Dockerfile,docker-compose.yml,.dockerignore}`,
  `app/docker-compose.yml`, `app/docker/nginx.conf`, and the orphaned workspace
  `.dockerignore`. The three sharp edges they needed comments for — `pdo_pgsql`
  needing a `-d` flag on the SAPI, compose interpolating `$var`, nginx resolving
  an upstream before Docker registered it — were sharp because of the stack.
- ~~`GET /.env` is a `404`, not a `403`~~ — `php -S` has no dotfile rule, so the
  request reaches the front controller. Still not served: `.env` is outside
  `public/`. Recorded because documents quoted the old status.
- ~~The verify skill's live check was rewritten~~ — it curled `/login` and
  `/dashboard`, routes the skeleton lost when the demo was stripped, on a
  container that no longer existed. That coverage now lives in
  `principle/tests/{ActionTest,TokenCodecTest,WorkerTest}` and `auth/tests`.

### 12. Request profiling is a module — 2026-10-13

`empira-debug-tools` ships as a sixth repository, wrapping
[Clockwork](https://github.com/itsgoingd/clockwork) rather than reimplementing
it. The framework names none of its classes and knows it exists.

- ~~A middleware, not a hook~~ — a profile is only complete once the response
  exists. A request that has not been answered has no status, no duration, and
  nothing to show the person debugging it, so the clock starts on the way in and
  the response is read on the way out. `Application::use()` is the seam; it
  already existed and rebuilds each layer per request, which is what keeps a
  source from carrying one request's objects into the next.
- ~~Nothing registered outside a development environment~~ — `APP_ENV` in
  `local`/`dev`/`development`/`test`. The decision governs **whether the routes
  exist**, not what they answer: in production there is no URL to probe and no
  directory to serve, rather than a URL that is present and refuses. Read from
  `$_ENV` first, like every other package here, with `getenv()` behind it — a
  profiler reaching a different answer about the environment than the application
  it profiles would be worse than one that never asked.
- ~~Payloads and cookies are not collected~~ — they are credentials, and this
  module writes request data to disk and serves that disk back over HTTP. Opt-in
  per instance, never a framework switch.
- ~~The interface serves assets out of `vendor/`~~ — Clockwork ships an
  installer that copies its UI into the application's `public/`, which would put
  a second copy of a third party's files where the application's own asset
  pipeline does not know about them. `Clockwork\Web\Web::asset()` reads them
  from the installed package instead: one copy, and it is the one Composer just
  installed.
- ~~`debug:show` and `debug:clear` are `CommandProvider` commands~~ — reachable
  whether or not the profiler is running, so `debug:clear` works *before* the
  thing you are debugging is fixed. `clear` truncates Clockwork's append-only
  `index` rather than deleting it, which the storage opens unsuppressed on every
  read.
- ~~`app/` does not install it~~ — the skeleton is what a consumer copies. It is
  `require --dev` in `app-test` only, with `bootstrap/modules.php` overlaid so the
  scratch app can prove a module installed the way an application installs one
  registers its routes and stays inert when it should.

Two things worth remembering about the wiring, both found by the suite rather than
by reading: `Clockwork::resolveRequest()` writes into whatever request object is
already on the instance and does not create one, so a shared `Clockwork` stored
every request under the first one's id; and `addDataSource()` appends without
checking for duplicates, so registering per request grows that list for the life
of the process.

**The skeleton's placeholder-`APP_SECRET` guard is not a profiler concern.** The suite in
`app-test` pins `APP_ENV` itself rather than inheriting it, because leaving it
ambient made four contract tests mean different things depending on who ran them
— and `production` is not available as a pin, since the skeleton refuses a
placeholder `APP_SECRET` outside a local environment. Unset is the off case, and
it is the one that matters: it is what an application that never heard of this
module looks like.

### 13. `.env` is the only source, and the secret has no default — 2026-10-13

Both about the same line: `App` stopped guessing its own configuration.

- ~~`App::SECRET` deleted~~ — it was public in three places (the source,
  `.env.example`, every clone on GitHub), so "change it before deploying" was a
  step every deployment had to remember. A fallback secret is a public constant
  with extra steps.
- ~~The placeholder guard is gone, not moved~~ — it fired only when `APP_ENV`
  was set to `production`, and unset counted as local, so setting neither served
  forgeable component URLs on every interface `composer serve` binds. The check
  depended on the other setting being right. `secret()` now requires
  `APP_SECRET` at ≥16 bytes **in every environment**, and distinguishes *not set*
  from *too short (N bytes)* because those are the two states a fresh copy of
  `.env.example` is actually in.
- ~~`loadEnvironment()` discards inherited values first~~ — the dotenv convention
  merges the other way, so an exported `APP_ENV` silently changed what a
  development machine served. Stripping `App::CONFIG_KEYS` before the merge means
  a key the file omits falls through to the application's own default rather than
  inheriting from outside.
- ~~`isLocal()` deleted~~ — it existed only to police the placeholder. `APP_ENV`
  now has **no reader in `app/`**; it is a convention consumed by modules that
  ask whether this is a development environment, `empira-debug-tools` among them.
- ~~`EMPIRA_BOOTSTRAP` is not in `CONFIG_KEYS`~~ — `Console\Bootstrap::locate()`
  reads it to decide which file to require, so it is consulted before the
  application has loaded anything. It cannot be file-sourced.
- ~~`module-contract` checks `app/` commits no `.env`~~ — and that `/.env` is
  still in its `.gitignore`. The ignore rule's absence is how a secret gets
  added, and that is invisible in a diff among dozens.
- ~~The suite's `tearDown` restores instead of unsetting~~ — `tests/bootstrap.php`
  loads the scratch application's `.env` once per process, so unsetting
  `APP_SECRET` in `tearDown` left every later test booting with none, which is
  now a refusal. A suite whose tests depend on their order reports failures
  nobody caused.

**The cost, stated once:** injected environment variables no longer reach the
application. `docker run -e`, Kubernetes `envFrom:`, systemd `Environment=` and CI
secrets are all ignored, because the value never arrives in `$_ENV`. A deployment
supplies a real `.env` instead — so the secret is on disk, in a file that belongs
to be mode `0600` and is never baked into an image layer. A fresh clone needs one
extra step before `composer serve`.

Found along the way, in the harness rather than in the plan:
`testAUserCanBePersistedThroughTheModulesEntityMapping` persisted a fixed address
unconditionally, so the suite was green only on the first run after a `reset` and
failed on every run after with a constraint violation that said nothing about
entity mapping. It clears the row first now.

### 14. The quoted baselines are checked — 2026-10-14

- ~~`dev/tools/check-baselines`~~ — a fourth guard. It runs the four library
  suites, parses `OK (N tests, M assertions)`, and diffs that against every
  baseline the documentation quotes. **38 claims across 35 files.**
- ~~The nine wrong numbers corrected~~ — `app-test` was quoted as 22 tests in the
  root `AGENTS.md` and 19 in `dev/`, from one workspace, on one day; debugtools as
  13, when it had been 38 since `f6db450`; and `dev/agents/app.md` said "the suite
  that ships is 17 tests" in the present tense, which was simply out of date.
- ~~Three lists were missing debugtools~~ — `dev/README.md`, `agents/workspace.md`
  and this file's Current line enumerated four suites when five exist, and
  `agents/debugtools.md` was the only package agent file with no status header.
- ~~`debugtools` added to `check-links`'s `PACKAGES`~~ — it was a published package
  that `module-contract` checked and `check-links` did not, so the rule that a
  package's relative links must not escape it was never applied to it. Already
  conformant, so this closed a gap rather than opening a failure.

**Two things the implementation had to get right, and one it had to give up.**

It runs the suites rather than counting `public function test` methods, because
`principle` declares 474 of them and reports **490** — two are data providers, a
16-test error on the largest suite. The other four agree exactly, which is a
coincidence and not a licence.

It matches the *shapes* a live baseline takes rather than every `\d+ tests`,
because seven of the numbers in these documents are genuinely about the past and a
broader scanner fails all seven. The only way to make it pass would be to delete
true sentences about why a thing changed — the failure `check-links` names as one
it would rather miss than cause. So a claim in an unrecognised shape is missed
rather than wrong.

It skips `app-test` rather than failing when the scratch application is absent,
because building it is a `git clone` over SSH. It names the baseline it could not
verify (32 claims checked instead of 38) instead of passing quietly.

---

## Deferred

- ~~Authentication~~ — shipped 2026-10-11. Guards, `UserProvider`, per-request
  `Auth`, `RequireAuth`, bearer and session guards. See
  [../../auth/README.md](../../auth/README.md).
- ~~CSRF exemption for token-authenticated routes~~ — shipped 2026-10-11. A
  valid bearer token is exempt because an `Authorization` header is not an
  ambient credential, plus a per-route `#[Route(csrf: false)]`. A
  session-authenticated POST with no token is still refused, and a test fails
  if that ever changes.
- ~~Explicit session write-back~~ — **shipped 2026-10-13.** `handle()` opened the
  session and never closed it, so the save was FrankenPHP's per-request teardown
  rather than ours. Verified working, and resting on undocumented runtime
  behaviour our own `beginRequest()` contradicts. At 2 × CPU a deferred
  write-back would lose a sign-in 31 times in 32. It is now `Session::endRequest()`
  in the `finally` that clears the context — symmetry with `beginRequest()`, and
  the lock and the write-back both belong to the request rather than to whichever
  request this worker serves next. Two tests in `WorkerTest.php`, both failing
  without the change. Design, evidence and the reasoning:
  [SPEC.md §4](SPEC.md#4-explicit-session-write-back-shipped).
- **Session isolation is only probabilistically checked at the default.** The
  stack ships 2 × CPU workers, and `verify` no longer pins one, so a per-worker
  state leak surfaces only when two consecutive requests for one session land on
  the same worker — about 1 in 32. Ten rounds catch roughly a quarter of such a
  bug. Re-run the check with `FRANKENPHP_WORKERS=1` whenever a state bug is
  suspected; the override is kept for exactly that.

### 15. htmx is served from `public/`, not a CDN — 2026-10-14

- ~~`app/public/htmx.min.js` committed~~ — htmx 2.0.4, 50 KB, byte-identical
  to upstream (`e209dda5c8235479f3166defc7750e1dbcd5a5c1808b7792fc2e6733768fb447`).
  Every page load used to fetch it from `unpkg.com`, putting a third party in
  front of the page and handing one a visitor's IP, referrer and timing.
- ~~`app/public/htmx.LICENSE` records the version and that hash~~ — beside the
  file rather than inside it, so the asset stays byte-identical and two upgrades
  stay diffable. htmx is **Zero-Clause BSD**, so vendoring carries no
  attribution obligation; the file says so rather than leaving it to be assumed.
- ~~`SiteLayout::htmxUrl()` overridden~~ — the override point already existed;
  nothing was added to the framework. **`Layout::htmxUrl()`'s default stays the
  CDN on purpose**, because a library cannot know where an application keeps its
  webroot, and defaulting to a local path would 404 for anyone who did not
  install from the skeleton.
- ~~A test asserts the script is local~~ — nothing else in the suite would notice
  a regression, since the markup would still be a correct `<script src>` pointing
  at somebody else's server. Confirmed by reverting the override and watching it
  fail.

**What changes for whoever upgrades it.** Pinning by URL meant a one-line edit;
a committed file means a snapshot in git history and a visible diff on upgrade.
Reviewable and revertible, which is the better trade, but it is a real change in
how the bump happens. SRI goes away — `integrity` guards against a compromised
CDN, and self-hosting is the fix rather than a mitigation of that.

**The gap this walked into, unchanged.** `app-test/` builds from `app/`'s
GitHub *clone*, so the first run of the new test failed — the working tree had
the change and the clone did not. Committing to `app/` first is the only way in,
the same constraint §9 records: a change to `app/` is uncovered until it is
pushed.

### 16. `public/` is served, or the skeleton has no htmx — 2026-10-15

- ~~`app/public/index.php` returns `false` for an existing file~~ — the whole
  application was dead under `composer serve`, and §15's asset is why.
- ~~`Serve.php`'s docblock corrected~~ — it claimed `php -S` "serve[s] the
  static files under `public/`". It does not.
- ~~A positive assertion in the verify skill's smoke test~~ — every other
  assertion in it was negative, proving what is *not* served and nothing about
  what is.
- ~~`module-contract` checks the guard~~ — it is here because the bug's shape is
  not "someone broke it" but "the skeleton ships a footgun to everyone who
  copies it and drops an image into `public/`".

**How it shipped.** `php -S -t public public/index.php` makes the front
controller a **router script**, and PHP runs a router at the start of every
request. Falling through to a static file is something the router has to ask for
by returning `false`; this one never did, so every asset in `public/` was
answered by the application, which has no route for it and 404s.

Invisible in production — nginx serves `public/` before PHP is involved — and
total on the dev server, because no htmx means no `hx-*` behaviour at all. And
latent until §15: `public/` held only `index.php`, so there was no static file
for the missing fall-through to matter about.

**The failure was process, not code.** The verification of §15 — *"start the
server, `curl` the file, assert 200"* — was written into a plan and then not run.
The asset shipped on the strength of a file existing and a hash matching. Both
were true, and neither was the question. The smoke test existed throughout and
was blind to it: a suite that proves nothing outside `public/` is served has no
opinion on whether anything inside it is.

That is worth more than the guard. **A `200` on an asset says the file was found,
not that the page works** — only loading `/` and clicking something says the
latter. The skill now says so, because the cheapest false positive in a
verification is one that looks like a pass.

### 17. `route:list` stopped fataling on every conventional application — 2026-10-15

- ~~`route:list` takes the application the console already booted~~ — it no
  longer requires a second one. `bin/empira` boots the project before it
  dispatches, so the application exists by the time any command runs.
- ~~`bootstrap/app.php` preferred to `src/App.php`~~ — when it does have to look
  for a file. The bootstrap is safe to require twice because it resolves classes
  through an autoloader; the class file never is.
- ~~Three tests~~ — the conventional layout had no coverage at all.

**The bug.** `vendor/bin/empira route:list` fataled with *"Cannot redeclare
class `App\App`"* for every application following the documented layout.
`bin/empira` requires `bootstrap/app.php`, which autoloads `src/App.php`;
`route:list` then defaulted to `src/App.php` itself, and `require` has no
include-once semantics, so the declaration ran twice. `migrate` and `serve` were
unaffected because the console hands them the booted application instead of
re-requiring anything.

**Two blind spots, either of which alone would have hidden it.** The console
tests call `Application->run()` in-process and never execute `bin/empira`, so
nothing was ever pre-required and the collision could not happen. And every
`route:list` fixture used `routes.php` or an explicit `--app=`, so the
conventional branch was never taken — while the test *named*
`testRouteListFindsTheApplicationInTheTargetDirectory` writes `routes.php`, so
its name promised the default and exercised the fallback.

**The one test that matters calls `Bootstrap::boot()` first**, because that is
the step the helper omits. Without it the test passes against the broken code;
confirmed by reverting the fix and watching it fail with the reported fatal.

**Same shape as §16.** Nothing in this workspace runs `vendor/bin/empira`. Any
defect depending on what the binary does before dispatch is invisible to the
suite, and the binary is where the project boot happens. A subprocess test is the
only thing that closes it, and it is **not written** — it needs a fixture with
`vendor/`, which is why this is recorded rather than quietly fixed twice.

### 18. `composer serve` binds loopback, and both scripts name the binary — 2026-10-15

- ~~`serve` no longer passes `--host=0.0.0.0`~~ — it binds loopback, so the
  placeholder secret stops being reachable from the network.
- ~~`serve` and `migrate` are both `./vendor/bin/empira …`~~ — the manifest held
  three invocation styles for one binary: `php vendor/bin/empira`,
  `vendor/bin/empira`, and now one. The file is executable with a shebang, so
  the explicit path is the shortest and says what it runs.

**Why the bind moved.** `APP_SECRET` signs every component URL, so a development
server reachable from the network is one where anything that can reach the port
can mint a signed component URL and have the application fetch any component with
any props. Nothing logs it and nothing 500s — the forged URL simply works. The
wide bind was accepted on the grounds that a VM is awkward to reach otherwise,
and the trade was then documented in five places rather than made once in the
manifest.

**The cost, stated.** A machine you reach from elsewhere now needs
`--host=0.0.0.0` spelled out. That is the point of the change rather than a cost
against it: the safe bind is the default and the exposed one is the thing you
have to ask for. The verify skill's smoke test curls `localhost`, so it never
depended on the wide bind — and binding every interface to look at a page is not
a trade any check should make.

**§13 is deliberately left alone**, where it says a missing `APP_ENV` "served
forgeable component URLs on every interface `composer serve` binds". That was
true then and is still true — the command binds whatever it is told — and §13
records why the secret is required in every environment, which a loopback
default does not weaken. Rewriting a historical entry because a default moved is
the thing this file's own rules exist to prevent.

### 19. `app-test serve` goes through the console — 2026-10-15

- ~~The harness's `serve` script is `./vendor/bin/empira serve --port=8001`~~ —
  it was `php -S localhost:8001 -t public public/index.php`, a raw server that
  bypassed the console entirely.
- ~~`dev/tools/app-test serve` forwards options after `--`~~ — so
  `serve -- --host=0.0.0.0` reaches the console, and a forwarded `--port` beats
  the script's.

**Why it mattered.** Loopback on 8001 is right, and the port is separate from the
skeleton's 8000 on purpose. But a raw `php -S` binds `localhost` and nothing
else, and **a command line cannot be widened after the fact** — `composer serve
-- --host=0.0.0.0` was silently ignored, because PHP's built-in server does not
take its arguments in that position. Serving the scratch application from WSL and
reaching it from a Windows browser was not possible through this tool, and
nothing said so.

It is now the same console the skeleton uses, and the console prints both
addresses — which is where the network address becomes visible at all.

**Still no dashboard.** `/_clockwork` is a 404 by design: the profiler package
ships no HTML and no JavaScript. The Clockwork Chrome extension needs only the
`X-Clockwork-*` headers and `/_clockwork/api/{id}`, and both were verified
reachable on loopback and on this machine's network address.

> Superseded by [§20](#20-clockworks-own-url-shape--2026-10-16) two days
> later: the paths are `/__clockwork` and `/__clockwork/{id}`. Left as written,
> because it was true when it was written.

---

### 20. Clockwork's own URL shape — 2026-10-16

- ~~`DebugController::PATH` is `/__clockwork`~~ — data is served at
  `/__clockwork/{id}` rather than `/_clockwork/api/{id}`, and
  `X-Clockwork-Path` reads `/__clockwork`.
- ~~Route ids constrained to `[0-9-]+|latest`~~ — load-bearing, not decorative.
- ~~`DebugController::API` removed~~ — it named a segment that stopped existing.
- ~~`debugtools/CHANGELOG.md` added~~ — `principle` was the only package with one.
- ~~`BootContractTest` asserts the exact patterns~~ — `/__clockwork` **and**
  `/__clockwork/{id:[0-9-]+|latest}`. A substring assertion would have passed for
  the asset route, which shares the prefix.
- ~~`testAnAssetPathIsNotSwallowedByTheIdRoute`~~ — **verified to fail** when the
  constraint is removed. The status alone cannot catch it, because an unknown id
  404s too; the content type is what distinguishes the two handlers.

**Two divergences, not one, and the second was the one that mattered.** The
prefix was `/_clockwork`; the data also hung off an `/api` segment Clockwork has
never had. The old shape was self-consistent and worked for a client reading
`X-Clockwork-Path` — but Clockwork's shipped JavaScript treats that header as an
*override* and otherwise constructs `/__clockwork/…` itself, which 404'd. Renaming
the prefix alone would have produced `/__clockwork/api/{id}`, still missing a
client expecting `/__clockwork/{id}`. Verified against installed Clockwork 5.3.5
rather than from memory: its Laravel provider registers four routes and none has
an `/api` segment.

Dropping `/api` reintroduced a collision it had been hiding by accident:
`/__clockwork/app/foo.js` matches `{id}` with `id = app`, and the asset route
becomes unreachable. `[0-9-]+|latest` is Clockwork's own shape — its Slim
middleware uses the same expression — and the framework splices
`{name:constraint}` in verbatim (`principle/src/Routing/Route.php:189`), so no
framework change was needed.

A test **asserted the old divergence as a requirement**:
`testThePathHeaderNamesTheApiBaseRatherThanThePrefix` asserted the header was
*not* the prefix. It was rewritten rather than mechanically updated, because the
premise inverted.

`DebugController`'s docblock justified the divergence — *"The same family is
reproduced under `PATH` so the prefix can be ours."* Choosing our own prefix was
the intent, and this gives it up, so the comment now says why rather than leaving
a rationale for the opposite decision.

**Verified live**, not only in tests: `X-Clockwork-Path: /__clockwork`;
`/__clockwork/{id}` → `200 application/json` and the returned `id` matches the
requested one; `/__clockwork/app/assets/index-159S-Gz-.js` → `200
application/javascript`, 522 KB; `/__clockwork/app/foo.js` → `404` from the asset
handler, not from `{id}`; `latest` and `/previous/5` → `200`. Baselines moved to
**39 tests at 123 assertions** and **26 app-test / 54**.

> Superseded a day later by [§21](#21-debugbar-replaces-clockwork--2026-10-16),
> which replaces the profiler entirely. The paths verified above are
> `/__clockwork/…` and no longer exist. Left as written, because it was true when
> it was written.

**Not automated.** The Clockwork Chrome extension is the one consumer that
ignores the header fallback path, so it is the only check that proves the
client-ignores-header case resolves. No browser is installed here; that check is
still owed.

### 21. DebugBar replaces Clockwork — 2026-10-16

- ~~`itsgoingd/clockwork` → `php-debugbar/php-debugbar:^3.8`~~ — and every
  Clockwork-shaped thing went with it: the `/__clockwork` routes, the
  `X-Clockwork-*` headers, `Server-Timing`, `EmpiraDataSource`,
  `ClockworkMiddleware`, `DebugController::API`, `DebugController::PATH`.
- ~~The toolbar is injected into full HTML documents~~ — which **reverses** the
  package's documented "injects nothing into your pages".
- ~~`Marks` kept, for a new reason~~ — `update()` and `Span::duration()` have no
  DebugBar equivalent, so the buffer earns its place again.
- ~~`debug:clear --hours=<n>`~~ — prunes by age. Closes the uncapped-growth risk.
- ~~45 debugtools tests / 125 assertions~~

**This inverts the module's central promise, so the boundary is drawn narrowly.**
`shouldInject()` requires no `HX-Request`, no vetoing content type, and a
`</body>` tag. The htmx rule is the load-bearing one: a toolbar appended to a
swap would land inside the element being replaced and duplicate on the next.
Each of the three was verified by deleting it and watching the suite go red.

**`Response::html()` sets no `Content-Type`.** Found while writing the gate:
the framework only sets one where something must be declared, so `text/html`
cannot be *required* — on the commonest HTML response there is no header at all.
The content type is only allowed to veto; `</body>` is what establishes a whole
page.

**`StandardDebugBar` is deliberately not used.** It registers
`RequestDataCollector`, which harvests `$_GET`, `$_POST` and `$_COOKIE` from the
superglobals **unconditionally**. Adopting it as-is would have written session
cookies and password fields to disk on every profiled request — precisely the
credential store the `collectPayloads`/`collectCookies` flags existed to prevent,
reintroduced by the upgrade. Collectors are assembled by hand instead, and a test
asserts the `request` key is absent and that neither secret is in the profile.

**A real bug, found only because the suite was run ten times.** `FileStorage`
orders stored requests by request id, and an id begins with a **millisecond**
timestamp — "lexical sort = time sort" for requests seconds apart. Two requests
inside one millisecond share that prefix and are ordered by the random bytes after
it, so `debug:show` could report the *previous* request. Two requests in a test
are microseconds apart, so this made the suite fail about one run in three. Both
the command and the test helpers now pick the maximum `__meta['utime']`.

**The mutation testing nearly lied twice.** A `perl -0pi` pattern that matched
nothing leaves a green suite behind, and reading only the "OK" line would have
counted it as a pass. The mutations were redone with a replacement counter
printed, and every one confirmed applied before its result was believed. An early
attempt at the same thing using `sed` also silently matched zero times.

FrankenPHP worker mode was left out of scope by decision. Nothing in the package
references it, and `DebugBar` is built per request, so there is no shared
instance to leak — dropping it cost a test, not a design.

**Not verified.** No browser here, so the toolbar has not been seen rendering.
The injection rules are asserted on response bodies, which is the contract; that
DebugBar's own JavaScript draws is upstream's to have tested.

### 22. PSR-7, PSR-11, PSR-15 and PSR-17 — 2026-10-16

- ~~`Container implements Psr\Container\ContainerInterface`~~ — `get()` and
  `has()` already existed.
- ~~`ContainerException` implements the PSR marker, no longer `final`~~
- ~~`NotFound extends ContainerException implements NotFoundExceptionInterface`~~
- ~~`Request implements ServerRequestInterface`, `Response implements ResponseInterface`~~
- ~~`Middleware extends MiddlewareInterface`, with `process()`~~
- ~~`Empira\Http\Psr17` — one object implementing all six factories~~
- ~~`Application::handle()` accepts a `ServerRequestInterface`~~
- ~~`PsrComplianceTest` — 22 tests, one per claim~~

**This is the package's own stated position applied to itself.**
`agents/principle.md:12` has said since the start that *"a dependency chosen
deliberately for the boring, standardised things is worth more than a hand-rolled
one that only this framework understands"* — which is why `psr/log` was adopted.
HTTP messages and middleware are the same category. `nyholm/psr7` supplies the
`Stream`, `Uri` and `UploadedFile` rather than hand-writing ~350 lines of exactly
the code that policy says to import.

**PSR-11 first, because it is nearly free and everything else builds on it.**
The exception split is PSR-11's own: "something went wrong" is `ContainerException`,
"there is nothing here" is `NotFound`, and `NotFound` extends the other so an
application catching `ContainerException` keeps catching what it caught.

### 23. A thin `App`, and modules named by class — 2026-10-16

- ~~`AuthModule` builds its own guard when it was given none~~ — naming a module
  by class produced one with no guards, no error, and a login form that reports
  "wrong password" forever
- ~~`Auth::attempt()` — one null for every failure, because distinguishing them
  tells an attacker which half of a guess was right~~
- ~~`ConfigurableModule` — a module says what options it accepts; core never
  names a module type~~
- ~~The module publishes `/login`, `/login` POST and `/logout`, with CSRF
  attached to the POSTs by the module rather than by the application~~
- ~~`App` is `final App extends Application` with `#[Modules]` and
  `#[Overrides]` above it — 369 lines down to a constructor~~
- ~~`Application` is no longer `final`~~
- ~~`bootstrap/modules.php` deleted from the skeleton and from the harness~~

**The module could not be named until it could build itself.** `array $guards = []`
is a perfectly good constructor default, so the container built an `AuthModule`
with nothing in it and said nothing. An application using the attribute would have
installed authentication that authenticated nobody — the exact failure the module
exists to prevent, and invisible in every log. Self-sufficiency is the
prerequisite, which is why this shipped before the `App` rewrite rather than with
it.

**A package cannot extend an application class, so `login:` means the application
supplies the page.** This is the one interpreted piece of the design and it is
worth stating plainly: `login:` names a layout class the module points `GET
/login` at, and the natural way to write it is one extending the app's own
`SiteLayout`. "The module uses the app's layout" is not something the package can
do — it ships a plain page as a floor and gets told about the real one.

**Two of the things found here were silent, and that is the shape worth
recording.** `ClassScanner` reported interfaces and traits as PSR-4 mismatches,
because `class_exists()` is false for both. `Application::dbConfig()` returned
`[]` whenever no `Config` was bound — which is always, since nothing binds one —
so `orm()` opened `:memory:` and every query answered. Neither errors. The
symptom in both cases is a thing that is *not there*: a table migrated into a
real file, a file sitting exactly where PSR-4 said. A missing thing is much
harder to notice than a broken one, and it is why the skeleton still needs a
suite generated for it even though it ships none.

**`orm($paths)` is now refused outright.** Rewriting `App` to drop its hand-built
config array left `$this->orm([__DIR__, $entityPath])` behind, which passes the
entity paths as the *configuration*. It shipped, booted and served — against a
database with no tables in it. The scratch suite caught it; `principle` now turns
it into a boot that stops.

## Three things the standards cost, none of which was obvious

**`withHeader()` used to mutate under a PSR-shaped name.** PSR-7 requires every
`with*` to clone. Two places discarded the return and were silently queueing nothing — both in `Application`, in
`errorResponse()` and in `renderException()`, each a bare
`$response->withHeader(...)` with no assignment. Found by reading, not by a test.

**`setBody()` and `setStatus()` were removed rather than aliased.** One immutable
way and one mutable way is how a codebase ends up with responses that half the
pipeline believes are shared.

**The component/context response had to be rebuilt.** `Component` held a *copy*
of `Context::$response`, which only worked because both were the same mutable
object. Under immutability the copy diverged and every `validate()` event and
every `pushUrl()` was lost — silently, because the tests were asserting on the
component's copy. `Context::$response` is now replaceable and is the single
answer; `Component` proxies to it and gained `updateResponse()`.

**`Request`'s constructor properties lost `readonly`.** PSR-7 needs `with*()` to
clone and PHP will not assign a readonly property on a clone. The alternative was
rebuilding fifteen methods through a nine-argument constructor, which puts the
maintenance burden of "a new field was added" on fifteen places instead of one.
Stated in the class docblock rather than buried.

**Full `process()` rather than a closure-preserving base class**, which was the
other option offered and declined. The cost is real and permanent: the parameter
is `ServerRequestInterface`, PHP forbids narrowing it, and the htmx conveniences
are therefore out of scope inside every middleware. `Request::of()` recovers them
in one line and **throws** rather than returning null — a middleware that quietly
degraded would work for everything except the htmx case, which is the one nobody
would notice until a swap stopped being one.

**`UploadedFile` keeps both shapes.** `files()` still answers with the
`$_FILES`-shaped array application code has always read; `getUploadedFiles()`
answers with PSR objects, built lazily so a request with fifty uploads opens no
handles for a consumer that never looks. Converting on construction would have
been the cleaner design and the bigger break.

**Verified:** third-party PSR-15 middleware naming no Empira class runs unadapted,
including short-circuiting; a factory-built request satisfies the standard and
keeps `path()`; `App-Test` rebuilds and passes at 26/56; a live page renders with
the toolbar injected and both profiler endpoints answering. **521 framework
tests / 1168 assertions**, and the suite was run three times rather than once —
immutability changes are exactly the shape that passes a single run while having
changed the semantics.

---

## Maintenance rules

1. Strike, never delete — the history is why this file is trustworthy.
2. Every shipped item gets a date.
3. If code and checklist disagree, the code is right and this file is the bug.
4. Anything marked `[planned]` that turns out to be wrong should be corrected **before** it is built, not after.