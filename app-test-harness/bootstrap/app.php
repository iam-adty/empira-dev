<?php

declare(strict_types=1);

/**
 * What `bin/empira` boots.
 *
 * Identical to the skeleton's own `bootstrap/app.php`, except for the two lines
 * that make this a scratch app rather than the thing that ships:
 *
 *     $app = App::boot(csrf: false);
 *     (require __DIR__ . '/harness.php')($app);   // ← the only addition
 *     return $app;
 *
 * Everything else is the skeleton's file, kept word for word so that a difference
 * in behaviour here is a difference in the harness and not an accident of editing
 * two near-identical files. If the skeleton's bootstrap changes, this is the file
 * that should change with it — and `dev/tools/app-test reset` is how you find out
 * that you did not.
 */

use App\App;

require __DIR__ . '/../vendor/autoload.php';

App::loadEnvironment(dirname(__DIR__));

$app = App::boot(csrf: false);

(require __DIR__ . '/harness.php')($app);

return $app;