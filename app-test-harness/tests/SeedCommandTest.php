<?php

declare(strict_types=1);

namespace App\Tests;

use App\App;
use Empira\Auth\User;
use Empira\Console;
use Empira\Console\Output;
use PHPUnit\Framework\TestCase;

/**
 * The seeder — the command that could not exist in `app/`.
 *
 * `app/composer.json` used to advertise `composer setup` and `composer seed`, both
 * of which failed: `@seed` called an `empira seed` command that did not exist, in
 * a project whose own test suite asserted that it registered no commands at all.
 * A manifest contradicting its own tests is worse than a missing feature, because
 * `composer setup` is the first thing anyone runs on a fresh clone.
 *
 * So the command lives here, where the scratch app can have a demo user and the
 * skeleton cannot drift into having one. These tests are why it is not just a
 * script nobody runs.
 */
final class SeedCommandTest extends TestCase
{
    private static string $database;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 2) . '/tmp';

        if (! is_dir($root)) {
            mkdir($root, 0o777, true);
        }

        self::$database = $root . '/empira-seed-test-' . bin2hex(random_bytes(6)) . '.sqlite';

        $_ENV['DB_DRIVER'] = 'sqlite';
        $_ENV['DB_DATABASE'] = self::$database;

        self::runConsole(['migrate']);
    }

    public static function tearDownAfterClass(): void
    {
        if (is_file(self::$database)) {
            unlink(self::$database);
        }
    }

    /**
     * Run one console command, require that it succeeded, and hand back what it
     * printed.
     *
     * Drives the console exactly as `bin/empira` does, including handing it the
     * booted application — a project command registered on the app is invisible to
     * a console that was not given it, so a shortcut here would "pass" while
     * testing a command the operator cannot run.
     *
     * @param list<string> $argv
     *
     * @return array{code: int, output: string}
     */
    private static function runConsole(array $argv): array
    {
        $previous = getcwd();
        $stream = fopen('php://memory', 'r+b');
        $output = new Output($stream, false);

        try {
            chdir(dirname(__DIR__));

            $console = new Console\Application();
            $booted = Console\Bootstrap::boot(dirname(__DIR__));

            if ($booted !== null) {
                foreach ($booted->commands() as $name => $command) {
                    $console->project($name, $command);
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

        self::assertSame(0, $exit, sprintf(
            '`empira %s` should succeed: %s',
            implode(' ', $argv),
            $printed,
        ));

        return ['code' => $exit, 'output' => $printed];
    }

    /**
     * The command has to exist at all. This is the assertion that would have
     * failed while `composer seed` pointed at nothing.
     *
     * It is booted through the console's own door, not `App::boot()` — the
     * harness registers the command from `bootstrap/app.php`, and a test that
     * bypassed that file would pass against a console the operator never runs.
     */
    public function testTheSeedCommandIsRegisteredAtBoot(): void
    {
        $app = Console\Bootstrap::boot(dirname(__DIR__));

        self::assertNotNull($app);

        $command = $app->commands()['seed'] ?? null;

        self::assertNotNull($command, 'the harness did not register a seed command');
        self::assertSame('seed', $command->name());
    }

    /**
     * Booting registers no commands at all: the seed comes from the harness, not
     * from the application.
     *
     * This used to compare against the skeleton in `../app`, and it read as though
     * it still did. It cannot: `App\App` resolves once per process, to *this*
     * project's copy, so requiring the skeleton's bootstrap still runs this
     * project's `App::boot()`. What was actually being asserted, both times, is
     * that boot registers nothing — which is worth knowing, and is what this says
     * now.
     *
     * That `app/` ships no test suite and no seeder is enforced where it can be
     * checked without loading a second copy of anything: `dev/tools/module-contract`.
     */
    public function testBootingRegistersNoCommandsOfItsOwn(): void
    {
        self::assertSame([], App::boot()->commands(), 'boot registered a command of its own');
    }

    public function testSeedingCreatesAUserThatCanBeVerified(): void
    {
        $email = 'seeded@example.com';

        self::runConsole(['seed', '--email=' . $email]);

        $app = App::boot();
        $entities = $app->entities();

        $user = $entities->getRepository(User::class)->findOneBy(['email' => $email]);

        self::assertInstanceOf(User::class, $user, 'the seeder did not persist a user');

        // A user whose password cannot be verified is not a seeded user; it is a
        // row that makes a login form report "wrong password" forever.
        self::assertNotSame('', $user->passwordHash());
        self::assertTrue(
            password_verify('correct horse battery staple', $user->passwordHash()),
            'the seeded hash does not verify against the documented password'
        );
    }

    /**
     * Idempotent, because a seeder you can only run once is a seeder you cannot
     * run while developing.
     */
    public function testSeedingTwiceIsNotAnError(): void
    {
        $email = 'twice@example.com';

        self::runConsole(['seed', '--email=' . $email]);
        self::runConsole(['seed', '--email=' . $email]);

        $app = App::boot();
        $users = $app->entities()
            ->getRepository(User::class)
            ->findBy(['email' => $email]);

        self::assertCount(1, $users, 'the second seed created a duplicate');
    }
}