<?php

declare(strict_types=1);

namespace App\Console;

use Empira\Application;
use Empira\Auth\DoctrineUserProvider;
use Empira\Auth\User;
use Empira\Console\Command;
use Empira\Console\Output;

/**
 * Creates one user, so the scratch app has something to sign in as.
 *
 * Idempotent, because a seeder you can only run once is a seeder you cannot run
 * while developing — and you will always run it twice by accident.
 *
 * ## Why this file exists only here
 *
 * `app/` cannot have a `seed` command. It is the skeleton a consumer copies, and
 * a demo user in it is a decision they inherit without asking; it used to ship a
 * `composer seed` that pointed at nothing while its own test suite asserted the
 * application registered no commands at all. So the command lives in the harness,
 * which `dev/tools/app-test reset` throws away and rebuilds, where being wrong
 * costs one command.
 *
 * ## How it gets registered
 *
 * By being here. The skeleton's `bootstrap/app.php` calls `discoverCommands()` on
 * `src/Console`, and this is the only class in it.
 *
 * It used to be registered by hand, from a `bootstrap/harness.php` that had to be
 * applied by a second copy of the skeleton's bootstrap. That arrangement had a
 * sharp edge worth recording: `Console\Bootstrap::boot()` `require`s
 * `bootstrap/app.php`, and `require` is not `require_once`, so that file was
 * included seven times over a suite run — and the class declared beside it was
 * declared seven times too. PHP fatals on the second. The class was moved into an
 * autoloaded `src/Console/` to fix it, and then the whole arrangement was
 * deleted, because a skeleton that discovers its own commands has no reason to
 * ship a harness that registers them.
 */
final class SeedCommand implements Command
{
    public function __construct(private readonly Application $app) {}

    public function name(): string
    {
        return 'seed';
    }

    public function description(): string
    {
        return 'Create the scratch application\'s demo user.';
    }

    public function arguments(): array
    {
        return ['[--email=address]'];
    }

    public function handle(array $options, Output $output): int
    {
        $email = (string) ($options['email'] ?? 'demo@example.com');
        $entities = $this->app->entities();

        if ($entities->getRepository(User::class)->findOneBy(['email' => $email]) instanceof User) {
            $output->info(sprintf('already seeded %s', $email));

            return 0;
        }

        // Hashed through the provider rather than with a bare password_hash(),
        // because the provider is what will later call password_verify() against
        // this value — and it is the one place that knows the cost.
        $hash = (new DoctrineUserProvider($entities))->hash('correct horse battery staple');

        $entities->persist(new User($email, 'Scratch User', $hash));
        $entities->flush();

        $output->success(sprintf('seeded %s', $email));

        return 0;
    }
}