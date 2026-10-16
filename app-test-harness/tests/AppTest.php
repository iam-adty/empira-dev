<?php

declare(strict_types=1);

namespace App\Tests;

use App\App;
use App\App\Modules;
use Empira\Auth\Auth;
use Empira\Auth\AuthModule;
use Empira\Auth\User;
use Empira\Console;
use Empira\DebugTools\DebugToolsModule;
use Empira\Http\Request;
use Empira\Module;
use PHPUnit\Framework\TestCase;
use ReflectionAttribute;
use ReflectionClass;

/**
 * The skeleton, proven to be a working application.
 *
 * This used to be 45 tests of a demo: sign-in, registration, rate limiting, a
 * dashboard, a nav bar. All of that code is gone, and so are the tests — a test
 * for a feature nobody ships is a test nobody reads and a suite that fails for
 * reasons unrelated to what it is meant to catch.
 *
 * What is left is the question a skeleton actually has to answer: *does this
 * boot, and does it serve?* That takes far fewer tests than it used to, and the
 * ones here are the ones whose absence would be a real problem — a page that
 * does not render, a module that does not install, a secret guard that does not
 * guard, a console that cannot build the schema.
 */
final class AppTest extends TestCase
{
    /**
     * The configuration keys a test is allowed to disturb, and the values they
     * had when the suite started.
     *
     * {@see self::$baseline} is read once, lazily, because it has to be
     * captured before the first test mutates anything — and capturing it in
     * `setUp()` would capture the previous test's leftovers.
     */
    private const CONFIG = ['APP_ENV', 'APP_SECRET'];

    /** @var array<string, string> */
    private static array $baseline = [];

    /** @var bool */
    private static bool $captured = false;

    private static function captureBaseline(): void
    {
        if (self::$captured) {
            return;
        }

        foreach (self::CONFIG as $key) {
            if (isset($_ENV[$key])) {
                self::$baseline[$key] = (string) $_ENV[$key];
            }
        }

        self::$captured = true;
    }

    /**
     * One database for the whole class, thrown away afterwards.
     *
     * Per test would be tidier in principle and ruinous in practice: hashing a
     * user with bcrypt costs about half a second, so a fresh file per test
     * turned a 0.07s suite into a 22s one.
     */
    private static string $database = '';

    public static function setUpBeforeClass(): void
    {
        // The shared `tmp/`, for the same reason the framework's suite has one: a
        // failing run leaves its database somewhere you would think to look.
        // Two levels up, which in the workspace is above every package — and
        // inside the container the workspace is mounted at `/workspace`, so the
        // same string resolves on the host and in the runner.
        //
        // A clone of this application alone writes `tmp/` next to itself, one
        // directory above wherever it was cloned. Untidy, harmless, and the
        // reason this creates the folder rather than assuming one exists.
        $root = dirname(__DIR__, 2) . '/tmp';

        if (! is_dir($root)) {
            mkdir($root, 0o777, true);
        }

        self::$database = $root . '/empira-app-test-' . bin2hex(random_bytes(6)) . '.sqlite';

        // The schema is prepared here rather than by the application booting,
        // because the application does not migrate on boot — and that is the
        // point of it. A suite that depends on boot to build its database cannot
        // survive the change that stops boot migrating.
        //
        // So this is the deploy step, run once, exactly as it would be in
        // production: `empira migrate`, against a database that is not there yet.
        $_ENV['DB_DRIVER'] = 'sqlite';
        $_ENV['DB_DATABASE'] = self::$database;

        self::runConsole(['migrate']);
    }

    /**
     * Remove the database this class created.
     *
     * The workspace `tmp/` is shared by all three suites and is documented as
     * holding nothing between runs — `ls -A tmp` is the check. A SQLite file
     * left behind is a stale schema that a later run reads as its own, and a
     * directory nobody trusts is a directory nobody checks.
     *
     * This method went missing when the suite was reduced from 45 tests to 15
     * and the fixture was rebuilt, which is exactly the kind of loss that a
     * shrinking suite invites: the lines that are not a test are the first
     * things to look optional.
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$database !== '' && is_file(self::$database)) {
            unlink(self::$database);
        }

        self::$database = '';
    }

    protected function setUp(): void
    {
        self::captureBaseline();
    }

    protected function tearDown(): void
    {
        if (isset($_SESSION)) {
            $_SESSION = [];
        }

        // Restored rather than unset. `tests/bootstrap.php` loads the scratch
        // application's `.env`, so `APP_ENV` and `APP_SECRET` arrive once per
        // process — and unsetting them here would leave every test after the
        // first booting with no secret at all, which is now a refusal rather
        // than a placeholder. A suite whose tests depend on their order is a
        // suite that reports a failure nobody caused.
        foreach (self::CONFIG as $key) {
            if (array_key_exists($key, self::$baseline)) {
                $_ENV[$key] = self::$baseline[$key];
            } else {
                unset($_ENV[$key]);
            }
        }
    }

    // ------------------------------------------------------------- serving

    public function testTheFrontPageRendersForEveryone(): void
    {
        $response = App::boot()->handle(new Request('GET', '/'));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('htmx lives in PHP classes', $response->body());
    }

    /**
     * The document is a `Layout` and the page contributes a region, so the page
     * arrives as markup rather than as a response body with a template applied.
     */
    public function testThePageIsRenderedThroughItsLayout(): void
    {
        $body = App::boot()->handle(new Request('GET', '/'))->body();

        self::assertStringContainsString('<html', $body);
        self::assertStringContainsString('id="region-main"', $body, 'the page did not render its own region');
        self::assertStringContainsString('<style', $body, 'the layout emitted no stylesheet');
    }

    /**
     * A full request renders the whole document; an htmx request renders only
     * the region it named. Same route, same layout, two shapes — and the second
     * is the entire reason this framework exists.
     */
    public function testAnHtmxRequestGetsOnlyTheRegionItAskedFor(): void
    {
        $body = App::boot()->handle(new Request(
            'GET',
            '/',
            headers: ['hx-request' => 'true', 'hx-target' => 'region-main'],
        ))->body();

        self::assertStringContainsString('id="region-main"', $body);
        self::assertStringNotContainsString('<html', $body, 'a fragment should not carry the document');
    }

    /**
     * htmx is served from this application's own `public/`, not a CDN.
     *
     * Nothing else in the suite would notice if that regressed: the markup
     * would still be a correct `<script src>`, just pointing at someone else's
     * server on every page load. So it is asserted here, where the skeleton is
     * what is under test.
     */
    public function testHtmxIsServedLocallyRatherThanFromACdn(): void
    {
        $body = App::boot()->handle(new Request('GET', '/'))->body();

        self::assertStringContainsString('src="/htmx.min.js"', $body);
        self::assertStringNotContainsString(
            'unpkg.com',
            $body,
            'the skeleton still points at the CDN, so every page load depends on a third party',
        );
    }

    public function testUnknownRoutesAre404(): void
    {
        self::assertSame(404, App::boot()->handle(new Request('GET', '/nope'))->status());
    }

    /**
     * The application's own error view, which is what a real 404 page is. It
     * replaces the body and must not disturb the status — a view that changed
     * the response's meaning would turn every refusal into a 200.
     */
    public function testTheErrorViewRendersWithoutChangingTheStatus(): void
    {
        $response = App::boot()->handle(new Request('GET', '/nope'));

        self::assertSame(404, $response->status());
        self::assertStringContainsString('404', $response->body());
    }

    // ------------------------------------------------------------- the module

    /**
     * The line this skeleton exists to show: authentication is installed, and
     * the framework binds its own read-only context without being told to.
     */
    public function testTheAuthModuleIsInstalledAtBoot(): void
    {
        // Unset, for the same reason as the command contract in BootContractTest:
        // the registry is the harness's, and what it installs depends on the
        // environment. Unset is the case that says "the skeleton, alone" — and
        // `production` would not boot, because the skeleton refuses a
        // placeholder APP_SECRET outside a local environment.
        unset($_ENV['APP_ENV']);

        try {
            $app = App::boot();

            // Two, not one: the harness overlay also installs the profiler, in
            // every environment. The assertion is about which module is there,
            // and an exact count of one would have been asserting that the
            // overlay was empty.
            self::assertCount(2, $app->modules());
            self::assertInstanceOf(AuthModule::class, $app->modules()[0]);
            self::assertCount(1, $app->guards(), 'the module registered no guard');
        } finally {
            unset($_ENV['APP_ENV']);
        }
    }

    /**
     * A module that decides to do nothing still counts as installed.
     *
     * The profiler registers its routes and its commands only in a development
     * environment, and this is the assertion that it is *in* the application
     * either way — the list of what an application is made of should not depend
     * on which environment you are asking in. A module that removed itself when
     * switched off would be a module whose installation nobody could verify.
     */
    public function testTheProfilerIsInstalledWhetherOrNotItIsRunning(): void
    {
        // `null` is unset rather than a name: it is the default an application
        // that never configured an environment boots under.
        foreach ([null, 'local'] as $env) {
            if ($env === null) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $env;
            }

            try {
                $app = App::boot();
                $label = $env ?? '(unset)';

                self::assertCount(2, $app->modules(), "expected the profiler to be installed under APP_ENV={$label}");
                self::assertInstanceOf(DebugToolsModule::class, $app->modules()[1]);
            } finally {
                unset($_ENV['APP_ENV']);
            }
        }
    }

    /**
     * The `#[Modules]` attribute is the answer to "what is this application made
     * of?", and this is what makes that true rather than aspirational.
     *
     * It names *classes* rather than instances, which is what lets the container
     * build them: the auth guard needs this application's session and entity
     * manager, and neither exists before `Application::orm()` has run. A list of
     * instances built eagerly at the top of a registry file would fail for
     * exactly that reason — which is why the attribute holds names and boot
     * resolves them once the container is ready.
     *
     * The two checks that matter: the attribute declares something, and what it
     * declares is what boot installed. If someone moves the module back inline
     * and leaves the attribute behind, the boot assertion still passes and this
     * one does not — which is the whole reason the declaration is the thing being
     * tested rather than the framework.
     */
    public function testTheModulesAttributeIsWhatDecidesWhichModulesExist(): void
    {
        $declared = (new ReflectionClass(App::class))
            ->getAttributes(Modules::class, ReflectionAttribute::IS_INSTANCEOF)[0]
            ->newInstance();

        // Strings, not instances: a name the container resolves is the whole
        // point, since the auth guard needs a session and an entity manager
        // that do not exist at the point an attribute is read. Asserting on
        // instances here would assert the opposite of the design.
        self::assertContainsOnly('string', $declared->modules);

        foreach ($declared->modules as $class) {
            self::assertTrue(is_a($class, Module::class, true), "{$class} is not a module");
        }

        self::assertSame(
            $declared->modules,
            array_map('get_class', App::boot()->modules()),
            'the attribute and the booted application disagree about the modules'
        );
    }

    /**
     * The module's façade resolves by type, because the module binds it — not
     * the framework. If that binding went away with the module, this is the test
     * that would notice, and the symptom in production is a container that
     * cannot build a type-hinting controller.
     */
    public function testTheModulePublishesItsOwnAuthFacade(): void
    {
        $app = App::boot();
        $app->handle(new Request('GET', '/'));

        self::assertInstanceOf(Auth::class, $app->container()->get(Auth::class));
    }

    /**
     * The `users` table ships with the module, so the schema arrived without
     * this application writing a migration. Proved by the deploy step above
     * succeeding against a database that did not exist — if the module's
     * migrations were not discovered, `users` would be absent and this would
     * throw rather than return null.
     */
    public function testTheModuleBroughtItsTableWithIt(): void
    {
        $app = App::boot();

        self::assertTrue(
            $app->entities()->getConnection()->createSchemaManager()->tablesExist(['users']),
            'the auth module\'s migration did not run — a table it owns is missing'
        );
    }

    public function testAUserCanBePersistedThroughTheModulesEntityMapping(): void
    {
        $app = App::boot();
        $repository = $app->entities()->getRepository(User::class);

        // Cleared first, because the claim being tested is that a user *can* be
        // persisted through this mapping — not that this address happens to be
        // free. The scratch application keeps one SQLite file between runs, so
        // without this the suite was green only on the first run after a
        // `reset` and failed on every run after it, with a constraint violation
        // that says nothing about entity mapping.
        $existing = $repository->findOneBy(['email' => 'skeleton@example.com']);

        if ($existing instanceof User) {
            $app->entities()->remove($existing);
            $app->entities()->flush();
        }

        $app->entities()->persist($user = new User('skeleton@example.com', 'Skeleton'));
        $app->entities()->flush();

        self::assertNotNull($user->id(), 'the User entity was not mapped, or not flushed');

        $app->entities()->clear();

        self::assertSame(
            'skeleton@example.com',
            $app->entities()->getRepository(User::class)->find($user->id())?->email()
        );
    }

    /**
     * `AuthModule::entityPath()` is load-bearing in a way that is easy to miss,
     * because Doctrine separates **discovery** from **lookup**.
     *
     * `getClassMetadata(User::class)` reflects on the class you *name*, so it
     * works whether or not the entity's directory was ever registered — which
     * is why the test above still passes with the path omitted, and why a
     * missing path can look like it costs nothing.
     *
     * `getAllClassNames()` is the discovery half, and it scans the registered
     * directories. It is what the schema tooling reads. So the failure mode of
     * forgetting the path is not an exception at request time; it is a schema
     * tool that quietly omits a table an entity depends on, and generates DDL
     * against a database that does not match the mapping.
     *
     * This test therefore asserts on discovery, which is the half that breaks.
     */
    public function testTheModuleEntityDirectoryIsDiscoveredNotMerelyReachable(): void
    {
        $discovered = self::discoveredClasses([dirname(__DIR__) . '/src', AuthModule::entityPath()]);

        self::assertContains(
            User::class,
            $discovered,
            'the module\'s entity directory was not registered, so schema tooling cannot see User'
        );
    }

    /**
     * Every class the given entity paths can *discover*, which is the set the
     * schema tooling works from.
     *
     * Built from the framework's own public factory rather than by reflecting
     * into Doctrine's `EntityManager`, which keeps the assertion honest: it asks
     * the configuration what it would scan, not what a particular manager
     * happens to have cached.
     *
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private static function discoveredClasses(array $paths): array
    {
        return \Empira\Persistence\EntityManagerFactory::configuration($paths)
            ->getMetadataDriverImpl()
            ->getAllClassNames();
    }

    // ------------------------------------------------------------- the secret

    public function testTheSecretComesFromTheEnvFile(): void
    {
        // The baseline is whatever `.env` supplied, via the suite's bootstrap.
        self::assertSame(
            self::$baseline['APP_SECRET'] ?? null,
            $_ENV['APP_SECRET'] ?? null,
            'the suite is not running against the application\'s own .env',
        );
    }

    public function testAnInheritedSecretIsIgnored(): void
    {
        // The whole point of the rule. `.env` is the only source, so a value
        // arriving through the process environment cannot reach the application —
        // and cannot change what it signs with. A test that sets one and watches
        // it do nothing is the only evidence that would survive a regression to
        // the old merge order.
        $_ENV['APP_SECRET'] = bin2hex(random_bytes(32));

        self::assertSame(200, App::boot()->handle(new Request('GET', '/'))->status());

        // And the application's own value is what a fresh read produces.
        unset($_ENV['APP_SECRET']);
        App::loadEnvironment(dirname(__DIR__));

        self::assertSame(self::$baseline['APP_SECRET'] ?? null, $_ENV['APP_SECRET'] ?? null);
    }

    public function testAMissingSecretRefusesToStart(): void
    {
        unset($_ENV['APP_SECRET']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_SECRET is not set');

        App::boot();
    }

    public function testATooShortSecretRefusesToStartAndSaysHowShort(): void
    {
        $_ENV['APP_SECRET'] = 'short';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('too short (5 bytes, 16 needed)');

        App::boot();
    }

    public function testTheRefusalNamesTheFileAndTheCommand(): void
    {
        unset($_ENV['APP_SECRET']);

        try {
            App::boot();

            self::fail('the application booted with no secret at all');
        } catch (\RuntimeException $e) {
            // A refusal that does not say what to do next is an obstacle, and
            // this is the first thing anybody meets who has just cloned the
            // skeleton.
            self::assertStringContainsString('.env', $e->getMessage());
            self::assertStringContainsString('random_bytes(32)', $e->getMessage());
        }
    }

    public function testAnEmptySecretIsRefusedRatherThanTreatedAsAbsent(): void
    {
        // `.env.example` ships `APP_SECRET=` empty, so an unset key and an empty
        // one are the two states a fresh copy is actually in, and they must fail
        // the same way rather than one of them quietly producing a zero-length
        // key.
        $_ENV['APP_SECRET'] = '';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_SECRET is not set');

        App::boot();
    }

    // ------------------------------------------------------------- the console

    /**
     * The deploy step, driven the way an operator types it.
     *
     * Run twice on purpose: a deploy step that is not idempotent fails the
     * second time a pipeline retries it, and a suite that only ever runs it once
     * cannot tell the difference between "works" and "works once".
     */
    public function testMigratingIsIdempotent(): void
    {
        self::assertStringContainsString('Nothing to migrate', self::runConsole(['migrate'])['output']);
    }

    /**
     * The skeleton registers no commands of its own; the scratch app adds exactly
     * one, and it is the one it is supposed to add.
     *
     * This used to assert that no `seed` existed anywhere, because the skeleton
     * used to ship a broken `composer seed` pointing at nothing. That assertion
     * belonged to `app/`, and it moved with the tests — but the scratch app
     * deliberately *does* have a seeder, so the same check now belongs here as a
     * check on the skeleton. See `SeedCommandTest`.
     */
    public function testTheScratchAppAddsExactlyTheOneCommandTheHarnessRegisters(): void
    {
        $output = self::runConsole(['list'])['output'];

        self::assertStringContainsString('seed', $output);

        // One, and only one. A harness that leaked a second command would be a
        // skeleton growing features again through the back door.
        $seeded = preg_match_all('/^\s*seed\s/m', $output);
        self::assertSame(1, $seeded, 'the harness should register `seed` and nothing else');
    }

    // ------------------------------------------------------------- helpers

    /**
     * Run one console command, require that it succeeded, and hand back what it
     * printed.
     *
     * Takes argv as an array rather than a command line, because the console
     * parses argv elements and not a string.
     *
     * @param list<string> $argv
     *
     * @return array{code: int, output: string}
     */
    private static function runConsole(array $argv): array
    {
        $previous = getcwd();
        $stream = fopen('php://memory', 'r+b');
        $output = new Console\Output($stream, false);

        try {
            chdir(dirname(__DIR__));

            $console = new Console\Application();

            // Exactly what `bin/empira` does, and the reason is not tidiness:
            // `migrate` plans across the migrations *modules* contribute, and it
            // learns those from the booted application. A test that drove the
            // console without handing it the booted app would migrate a schema
            // with no `users` table — a failure that points at the database
            // rather than at the wiring that was missing.
            $booted = Console\Bootstrap::boot(dirname(__DIR__));

            if ($booted !== null) {
                foreach ($booted->commands() as $name => $projectCommand) {
                    $console->project($name, $projectCommand);
                }

                $console->booted($booted);
            }

            $exit = $console->run(array_merge(['empira'], $argv), $output);
        } finally {
            chdir((string) $previous);
        }

        rewind($stream);
        $printed = trim((string) stream_get_contents($stream));
        fclose($stream);

        self::assertSame(0, $exit, sprintf('`empira %s` should succeed: %s', implode(' ', $argv), $printed));

        return ['code' => $exit, 'output' => $printed];
    }
}