<?php

declare(strict_types=1);

/**
 * The scratch application's module registry.
 *
 * ## Why this file replaces the skeleton's rather than sitting beside it
 *
 * `dev/tools/app-test` copies the harness over the clone, so a file here is the
 * file `app-test/bootstrap/modules.php`. There is no merge: this is the whole
 * registry, which is why `AuthModule` is repeated below even though the skeleton
 * already registers it. Editing the clone's copy instead would be undone by the
 * next `reset`, and the skeleton's copy is not the skeleton's to change — it is
 * shipped to whoever copies `app/`, and a debugger in it would be a dependency
 * they inherited without asking.
 *
 * ## What the scratch app proves that the package's own suite cannot
 *
 * That a module installed the way an application installs one — named in a
 * registry, resolved from the container, with nothing else aware of it —
 * registers its routes, contributes its commands, and is inert when the
 * environment says it should be.
 */

use Empira\Application;
use Empira\Auth\AuthModule;
use Empira\Auth\DoctrineUserProvider;
use Empira\Auth\SessionGuard;
use Empira\DebugTools\DebugToolsModule;

return function (Application $app): array {
    return [
        new AuthModule([
            new SessionGuard($app->session(), new DoctrineUserProvider($app->entities())),
        ]),

        // The application's base path, so recorded requests land in the scratch
        // app's own storage/ rather than wherever the suite happened to be run
        // from. Nothing else about the profiler is configured: leaving it to
        // APP_ENV is the point — the skeleton has no opinion about it.
        new DebugToolsModule(dirname(__DIR__)),
    ];
};
