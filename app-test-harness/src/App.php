<?php

declare(strict_types=1);

namespace App;

use App\Pages\LoginLayout;
use Empira\Application;
use Empira\Application\EnvSecret;
use Empira\Application\Modules;
use Empira\Application\Overrides;
use Empira\Application\ProvidesLogger;
use Empira\Application\ProvidesSecret;
use Empira\Application\ProvidesStorage;
use Empira\Application\SqliteStorage;
use Empira\Application\StderrLogger;
use Empira\Auth\AuthModule;
use Empira\Auth\LoginPage;
use Empira\DebugTools\DebugToolsModule;
use Empira\Security\Csrf;
use Empira\State\SessionState;
use Empira\State\StateStore;

/**
 * The scratch application's `App` — written out, not inherited.
 *
 * ## Why this file exists at all
 *
 * The skeleton ships its own `App\App`, and this replaces it wholesale. So the
 * two cannot share: this is `App\App`, and the skeleton's is not there to extend.
 *
 * That duplication is the point rather than a cost. A skeleton whose `App` class
 * its own test suite reuses proves nothing about whether that class is *usable* —
 * it only proves the shape compiles. Writing the wiring out again, the way
 * somebody who copied `app/` would, is what makes this file worth having: if the
 * constructor stops working for a real consumer, this suite is what notices.
 *
 * It noticed twice on the first pass — a missing `loadEnvironment()`, and `orm()`
 * handed entity paths in its configuration argument.
 *
 * ## What is not duplicated
 *
 * The five private helpers this used to carry — `attribute()`, `override()`,
 * `basePath()`, `publishDatabaseDefaults()`, `resolveModules()` — now live on
 * `Application`, because none of them is an application's business. They were
 * the reason the two copies drifted. Everything left here is a decision.
 *
 * ## What this file adds to the skeleton
 *
 * One line: `DebugToolsModule` joins the list. It is `require-dev` here, so it
 * is absent from a production install — and named explicitly, because a
 * development tool nobody asked for has no business being in the skeleton.
 *
 * Its storage follows `getcwd()`, which is this application's root: the suite
 * runs from here, so the recordings land in `storage/` beside the code. The
 * skeleton's registry passed `dirname(__DIR__)` to say that; the module defaults
 * to the same answer.
 */
#[Modules(AuthModule::class, DebugToolsModule::class)]
#[LoginPage(LoginLayout::class)]
#[Overrides(secret: EnvSecret::class, logger: StderrLogger::class, storage: SqliteStorage::class)]
final class App extends Application
{
    /** @var list<string> */
    private const CONFIG_KEYS = [
        'APP_ENV',
        'APP_SECRET',
        'LOG_STREAM',
        'LOG_LEVEL',
        'DB_DRIVER',
        'DB_DATABASE',
    ];

    public static function boot(?StateStore $state = null): static
    {
        return new static($state, new Csrf());
    }

    public static function bootConsole(?StateStore $state = null): static
    {
        return new static($state);
    }

    /**
     * The skeleton's own keys, handed to the framework's method.
     *
     * The mechanism is inherited now, which is the point: it used to be copied
     * here and drifted, and `bootstrap/app.php` — which comes from the clone,
     * not from this overlay — calls it with no arguments.
     *
     * @param list<string> $keys further keys to strip before reading the file
     */
    public static function loadEnvironment(array $keys = [], ?string $basePath = null): void
    {
        parent::loadEnvironment([...self::CONFIG_KEYS, ...$keys], $basePath);
    }

    public function __construct(?StateStore $state = null, ?Csrf $csrf = null)
    {
        $overrides = self::attribute(Overrides::class);

        self::publishDatabaseDefaults(self::basePath(
            self::override($overrides->storage, ProvidesStorage::class)->path(),
        ));

        parent::__construct(
            secret: self::override($overrides->secret, ProvidesSecret::class)->secret(),
            state: $state ?? new SessionState(),
            csrf: $csrf,
        );

        $this->components(__NAMESPACE__ . '\\');

        $this->orm(null, [__DIR__, AuthModule::entityPath()]);

        $this->installModules(self::attribute(Modules::class)->modules);

        $this->log(self::override($overrides->logger, ProvidesLogger::class)->logger());

        $this->discover(__DIR__, __NAMESPACE__);

        $this->onHttpError(static fn (int $status, string $message): string => sprintf(
            '<!doctype html><meta charset="utf-8"><title>%d</title><h1>%d</h1><p>%s</p>',
            $status,
            $status,
            htmlspecialchars($message, ENT_QUOTES),
        ));
    }
}