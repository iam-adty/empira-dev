<?php

declare(strict_types=1);

namespace App\Tests;

use App\App;
use Empira\Console\Command;
use PHPUnit\Framework\TestCase;

/**
 * One assertion about what a boot is allowed to contain.
 *
 * ## Why this file is not named after the seeder
 *
 * It was. It held four tests, and three of them tested `App\SeedCommand` — a demo
 * seeder that lives in a scratch application `dev/tools/app-test reset` throws
 * away. Testing disposable code is testing the scaffolding, and two of those
 * tests also said nothing the framework's own `ProjectCommandTest` does not:
 * that a project command runs when it is named, that it is listed as the
 * project's, that a duplicate name is refused. Deleting them lost no coverage of
 * shipped code and cut the suite by three.
 *
 * What remained is the part nothing else checks: that booting registers no
 * commands of its own. `module-contract` catches a `seed` **script** reappearing
 * in the skeleton; it cannot catch a command registered inside `App::boot()`,
 * which needs no script at all.
 *
 * ## What still covers the harness
 *
 * `AppTest::testTheScratchAppAddsExactlyTheOneCommandTheHarnessRegisters` boots
 * the console and asserts the seeder is listed. So the two together say the whole
 * thing: *boot registers nothing, and the harness adds exactly `seed`.*
 *
 * ## Why there is no database and no console here
 *
 * Because there is nothing to run. This file no longer opens a SQLite fixture,
 * which means it no longer writes to the shared `tmp/`, and it is why deleting
 * these tests made the suite both smaller and cleaner.
 */
final class BootContractTest extends TestCase
{
    public function testBootingRegistersNoCommandsOfItsOwn(): void
    {
        // Pinned, because "no commands" is only true of a skeleton whose
        // modules have nothing to contribute — and which modules contribute
        // depends on the environment. Left to `$_ENV`, this assertion silently
        // changes meaning for whoever runs the suite with `APP_ENV=local` set,
        // which is the environment a developer is most likely to have exported.
        self::withoutProfiler(function (): void {
            // Scoped by origin rather than by emptiness. A module may contribute
            // commands — this overlay installs one that does, in every
            // environment, so that `debug:clear` works before anything has been
            // recorded — and the claim being tested is about the *skeleton*:
            // nothing a consumer copied from `app/` registers itself. A command
            // from the `App\` namespace appearing here is that failure, and an
            // exact list would call it a pass while a module added a command.
            self::assertSame(
                [],
                self::ownCommands(App::boot()->commands()),
                'boot registered a command of its own',
            );
        });
    }

    /**
     * Commands declared by the application, as opposed to contributed to it.
     *
     * @param array<string, Command> $commands
     * @return array<string, Command>
     */
    private static function ownCommands(array $commands): array
    {
        return array_filter(
            $commands,
            static fn (Command $command): bool => str_starts_with($command::class, 'App\\'),
        );
    }

    /**
     * The other side of the same contract.
     *
     * The skeleton registers nothing of its own, so a command here can only have
     * come from a module — which is what makes the two assertions together a
     * statement about the skeleton rather than about the harness's overlay.
     */
    public function testAModuleContributesCommandsWithoutTheSkeletonHelping(): void
    {
        self::withProfiler(function (): void {
            $commands = array_keys(App::boot()->commands());

            self::assertContains('debug:show', $commands);
            self::assertContains('debug:clear', $commands);
            self::assertNotContains('seed', $commands, 'the seeder is discovered, not registered by boot');
        });
    }

    /**
     * The routes are the part that matters, because they are the part that is
     * reachable.
     */
    public function testTheProfilerIsAbsentUnlessTheEnvironmentAsksForIt(): void
    {
        $patterns = fn (): array => array_map(
            static fn ($route): string => $route->pattern,
            App::boot()->routes()->all(),
        );

        // The API route, not `/_clockwork` itself. The module serves no page —
        // nothing is injected into a response and no JavaScript ships — so the
        // data endpoint is the whole of what it publishes, and that is what has
        // to be missing when it is off.
        self::withoutProfiler(function () use ($patterns): void {
            self::assertNotContains('/_clockwork/api', $patterns());
        });

        self::withProfiler(function () use ($patterns): void {
            self::assertContains('/_clockwork/api', $patterns());
        });
    }

    /**
     * The console boot is not a smaller application.
     *
     * `empira migrate` plans across every module's migrations and `route:list`
     * reads the route table, so a console that saw less than the web would
     * migrate a schema missing every table a module owns — and would fail at
     * runtime, on the first query, a long way from the cause. One private
     * `build()` is what stops the two drifting; this is the test for it.
     */
    public function testTheConsoleBootSeesWhatTheWebBootSees(): void
    {
        $web = App::boot();
        $console = App::bootConsole();

        self::assertCount(count($web->modules()), $console->modules());
        self::assertCount(count($web->routes()->all()), $console->routes()->all());

        // The ORM is configured by the same call in both, so it has to be
        // configured rather than merely absent.
        self::assertSame(
            $web->entities()->getConnection()->getParams(),
            $console->entities()->getConnection()->getParams(),
            'the console booted against a different database',
        );
    }

    /**
     * And it registers nothing either, so `bootstrap/app.php`'s
     * `discoverCommands()` is the only thing that can put a command in a
     * skeleton.
     */
    public function testTheConsoleBootRegistersNoCommandsOfItsOwn(): void
    {
        self::withoutProfiler(function (): void {
            // Empty, and it is the console that has to be empty here. The
            // harness's seeder is found by `bootstrap/app.php` calling
            // `discoverCommands()`, not by booting — so a skeleton that wanted a
            // command would get one by dropping a file into `src/Console`, and
            // that file is the thing to watch, not this count.
            //
            // `AppTest::testTheScratchAppAddsExactlyTheOneCommandTheHarnessRegisters`
            // is the other half: boot adds nothing, the bootstrap adds `seed`.
            self::assertSame([], self::ownCommands(App::bootConsole()->commands()));
        });
    }

    /**
     * Runs a callback with `APP_ENV` pinned to `local`, and puts it back after.
     *
     * Both channels are set, because the profiler reads both: `$_ENV` is what
     * every package here reads, and `getenv()` is the fallback for a variable
     * that arrived without populating the superglobal. Clearing only one leaves
     * a suite whose result depends on whether the developer who ran it had the
     * variable exported — which is exactly the ambient-state problem these tests
     * exist to remove.
     *
     * @param callable(): void $test
     */
    private static function withProfiler(callable $test): void
    {
        self::pinEnvironment('local', $test);
    }

    /**
     * The profiler's off case, which is the default: no `APP_ENV` at all.
     *
     * Not `production`. That reads like the obvious opposite, and the skeleton
     * refuses to boot with a placeholder `APP_SECRET` outside a local
     * environment — so pinning `production` here would test the secret guard's
     * exception message instead of the profiler. Unset is both what an
     * application that never heard of this module looks like, and the case the
     * profiler has to get right by default.
     *
     * @param callable(): void $test
     */
    private static function withoutProfiler(callable $test): void
    {
        self::pinEnvironment(null, $test);
    }

    /**
     * @param callable(): void $test
     */
    private static function pinEnvironment(?string $value, callable $test): void
    {
        $superglobal = $_ENV['APP_ENV'] ?? null;
        $process = getenv('APP_ENV');

        self::writeEnvironment($value);

        try {
            $test();
        } finally {
            self::writeEnvironment($superglobal);
            is_string($process) ? putenv("APP_ENV={$process}") : putenv('APP_ENV');
        }
    }

    /**
     * Writes both channels, or clears both when given null.
     *
     * Clearing only the superglobal leaves a suite that still sees whatever the
     * shell exported — which is the failure this whole arrangement exists to
     * prevent, reached by a different road.
     */
    private static function writeEnvironment(?string $value): void
    {
        if ($value === null) {
            unset($_ENV['APP_ENV']);
            putenv('APP_ENV');

            return;
        }

        $_ENV['APP_ENV'] = $value;
        putenv("APP_ENV={$value}");
    }
}
