<?php

declare(strict_types=1);

namespace App\Tests;

use App\App;
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
        self::assertSame([], App::boot()->commands(), 'boot registered a command of its own');
    }
}