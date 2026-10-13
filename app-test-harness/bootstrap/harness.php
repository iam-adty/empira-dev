<?php

declare(strict_types=1);

/**
 * The scratch app's own additions.
 *
 * `app/` ships without this file, without a `seed` command and without tests — it
 * is a skeleton, and a skeleton that grows a demo stops being one. Seeding is a
 * dirty operation: it writes rows, needs a database, and has opinions about what a
 * "demo user" is. So it lives here, in the harness that `dev/tools/app-test reset`
 * throws away and rebuilds.
 *
 * The scratch app is the skeleton plus exactly three files:
 *
 *   - `bootstrap/app.php`   — the skeleton's own, with one line added
 *   - `bootstrap/harness.php` — this file
 *   - `src/SeedCommand.php` — the seeder itself
 *
 * Nothing in the skeleton's `src/App.php` is patched, which is the point: a
 * generator that edited it would be one line away from silently not working after
 * any upstream change to that file, and would fail by producing a subtly different
 * app rather than by erroring.
 *
 * ## Why this returns a closure instead of doing the work
 *
 * Because the application it decorates is an argument, and because this file is
 * included by a `require` that runs more than once per process. `App::boot()`
 * happens inside `bootstrap/app.php`; a file that registered commands at load time
 * would have no application to register them on.
 *
 * ## Why there is nothing declared in here at all
 *
 * Because `bootstrap/app.php` is re-included on every `Console\Bootstrap::boot()`,
 * and so is this file, transitively. A class declared at this file's scope would
 * be declared again on the second console command and PHP would stop the process.
 * That is not a hypothetical — it is what happened when {@see \App\SeedCommand}
 * lived here. Keep this file to a `require`, a closure, and comments.
 *
 * @return callable(\Empira\Application): void
 */

use App\SeedCommand;
use Empira\Application;

return static function (Application $app): void {
    $app->command('seed', new SeedCommand($app));
};