<?php

declare(strict_types=1);

/**
 * The suite's bootstrap.
 *
 * Loads the scratch application's `.env` before any test runs, so a test sees the
 * same environment a served request would. Without this the suite ran against a
 * bare `$_ENV` — no `APP_SECRET`, no `APP_ENV` — while `composer serve` had both,
 * which is the kind of difference that only shows up as a failure nobody can
 * reproduce.
 *
 * It is also what makes the missing-secret tests possible: with the file loaded,
 * "no secret" is something a test has to arrange deliberately rather than
 * something it inherits by accident.
 *
 * Loading it twice is harmless — `loadEnvironment()` is idempotent for the same
 * file — but PHPUnit runs this once per process, which is once.
 */

use App\App;

require dirname(__DIR__) . '/vendor/autoload.php';

App::loadEnvironment(dirname(__DIR__));
