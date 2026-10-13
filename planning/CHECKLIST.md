# Checklist

> **Status: live.** Checked items are struck, not deleted — the history is the
> point. If an item here disagrees with the code, the code wins and this file is
> a bug.

Framework: `iam-adty/empira-principle` · app: `iam-adty/empira-app`
Current: **487 framework tests / 1089 assertions**, **37 auth tests / 71 assertions**, **14 frankenphp tests / 24 assertions**, **19 app-test tests / 35 assertions** (in the generated `app-test/`, not in `app/`).

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
  were being spent on 480 tests that open a file. The suite is 487 tests in 0.7s
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

---

## Maintenance rules

1. Strike, never delete — the history is why this file is trustworthy.
2. Every shipped item gets a date.
3. If code and checklist disagree, the code is right and this file is the bug.
4. Anything marked `[planned]` that turns out to be wrong should be corrected **before** it is built, not after.