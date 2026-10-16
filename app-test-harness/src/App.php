<?php

declare(strict_types=1);

namespace App;

use App\Pages\LoginLayout;
use Empira\Application;
use Empira\Application\EnvSecret;
use Empira\Application\LoginPage;
use Empira\Application\Modules;
use Empira\Application\Overrides;
use Empira\Application\ProvidesLogger;
use Empira\Application\ProvidesSecret;
use Empira\Application\ProvidesStorage;
use Empira\Application\SqliteStorage;
use Empira\Application\StderrLogger;
use Empira\Auth\AuthModule;
use Empira\Config\Env;
use Empira\ConfigurableModule;
use Empira\DebugTools\DebugToolsModule;
use Empira\Security\Csrf;
use Empira\State\SessionState;
use Empira\State\StateStore;

/**
 * The scratch application's `App` — written out, not inherited.
 *
 * ## Why this file exists at all
 *
 * The skeleton ships its own `App\App`, and this replaces it wholesale, the same
 * way the `bootstrap/modules.php` this file deletes used to. So the two cannot
 * share: this is `App\App`, and the skeleton's is not there to extend.
 *
 * That duplication is the point rather than a cost. A skeleton whose `App` class
 * its own test suite reuses proves nothing about whether that class is *usable* —
 * it only proves the shape compiles. Writing the wiring out again, the way
 * somebody who copied `app/` would, is what makes this file worth having: if the
 * constructor below stops working for a real consumer, this suite is what
 * notices.
 *
 * The three files the skeleton's own `App` shares with it — `EnvSecret`,
 * `StderrLogger`, `SqliteStorage` and their contracts — are not duplicated,
 * because the clone provides them and they are configuration rather than
 * wiring. Duplicating those would be inventing a difference that does not exist.
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
    public static function boot(?StateStore $state = null): static
    {
        return new static($state, new Csrf());
    }

    public static function bootConsole(?StateStore $state = null): static
    {
        return new static($state);
    }

    /**
     * Load `.env` into `$_ENV`, before anything reads configuration from it.
     *
     * Copied from the skeleton verbatim, because `bootstrap/app.php` calls this
     * and a copy that drifted would fail on a message about a missing method
     * rather than about the drift. That is what happened the first time this
     * file was written — the method was left out, and the suite could not start.
     * Which is the file doing its job.
     *
     * @var list<string>
     */
    private const CONFIG_KEYS = [
        'APP_ENV',
        'APP_SECRET',
        'LOG_STREAM',
        'LOG_LEVEL',
        'DB_DRIVER',
        'DB_DATABASE',
    ];

    public static function loadEnvironment(string $basePath): void
    {
        foreach (self::CONFIG_KEYS as $key) {
            unset($_ENV[$key]);
        }

        $_ENV = array_merge($_ENV, Env::fromFile($basePath . '/.env'));
    }

    public function __construct(?StateStore $state = null, ?Csrf $csrf = null)
    {
        $overrides = self::attribute(Overrides::class);
        $modules = self::attribute(Modules::class);

        self::publishDatabaseDefaults(self::resolveStorage(
            self::override($overrides->storage, ProvidesStorage::class)->path(),
        ));

        parent::__construct(
            secret: self::override($overrides->secret, ProvidesSecret::class)->secret(),
            state: $state ?? new SessionState(),
            csrf: $csrf,
        );

        $this->components(__NAMESPACE__ . '\\');

        $this->orm(null, [__DIR__, AuthModule::entityPath()]);

        $this->module(...$this->resolveModules($modules->modules));

        $this->log(self::override($overrides->logger, ProvidesLogger::class)->logger());

        $this->discover(__DIR__, __NAMESPACE__);

        $this->onHttpError(static fn (int $status, string $message): string => sprintf(
            '<!doctype html><meta charset="utf-8"><title>%d</title><h1>%d</h1><p>%s</p>',
            $status,
            $status,
            htmlspecialchars($message, ENT_QUOTES),
        ));
    }

    /**
     * @return list<\Empira\Module>
     */
    private function resolveModules(array $classes): array
    {
        $login = self::attribute(LoginPage::class, required: false);

        $options = $login instanceof LoginPage ? ['login' => $login->page] : [];

        return array_map(function (string $class) use ($options): \Empira\Module {
            $module = $this->container()->get($class);

            if ($module instanceof ConfigurableModule) {
                $module->configure($options);
            }

            return $module;
        }, $classes);
    }

    private static function resolveStorage(string $path): string
    {
        return str_starts_with($path, '/') ? $path : dirname(__DIR__) . '/' . $path;
    }

    private static function publishDatabaseDefaults(string $path): void
    {
        foreach (['DB_DRIVER' => 'sqlite', 'DB_DATABASE' => $path] as $key => $value) {
            if (! isset($_ENV[$key]) || $_ENV[$key] === '') {
                $_ENV[$key] = $value;
            }
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param class-string $contract
     * @return T
     */
    private static function override(string $class, string $contract): object
    {
        if (! is_a($class, $contract, true)) {
            throw new \LogicException(sprintf(
                '#[Overrides] named %s, which does not implement %s.',
                class_exists($class) ? $class : sprintf('%s (no such class)', $class),
                $contract,
            ));
        }

        return new $class();
    }

    private static function attribute(string $name, bool $required = true): ?object
    {
        $found = (new \ReflectionClass(static::class))->getAttributes($name, \ReflectionAttribute::IS_INSTANCEOF);

        if ($found === []) {
            if (! $required) {
                return null;
            }

            throw new \LogicException(sprintf(
                '%s must declare #[%s] — an application with neither has nothing to build itself from.',
                static::class,
                $name,
            ));
        }

        return $found[0]->newInstance();
    }
}