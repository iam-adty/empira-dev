<?php

declare(strict_types=1);

namespace App;

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
 * which `dev/tools/app-test` throws away and rebuilds, where being wrong costs one
 * command.
 *
 * ## Why it lives in `src/` and not in `bootstrap/harness.php`
 *
 * Because `bootstrap/app.php` is re-required, not autoloaded.
 * `Console\Bootstrap::boot()` says `$booted = require $file`, and the suite runs
 * several console commands in one PHP process — so that file is included seven
 * times over a run, and anything it includes is included with it. A class declared
 * at file scope therefore gets declared seven times, and PHP fatals on the second:
 *
 *     Cannot declare class SeedCommand, because the name is already in use
 *
 * The skeleton's own bootstrap survives this by declaring nothing. An autoloaded
 * file is required exactly once no matter how many times anything reaches for it,
 * which is the only reason this command can live in a project whose entry point is
 * an `include`. **If this is ever moved back into the bootstrap path, it breaks on
 * the second command rather than on the first test.**
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