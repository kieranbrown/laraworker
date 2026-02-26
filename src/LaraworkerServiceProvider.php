<?php

namespace Laraworker;

use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;
use Laraworker\Console\BuildCommand;
use Laraworker\Console\DeleteCommand;
use Laraworker\Console\DeployCommand;
use Laraworker\Console\DevCommand;
use Laraworker\Console\InstallCommand;
use Laraworker\Console\MigrateGenerateCommand;
use Laraworker\Console\StatusCommand;

class LaraworkerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laraworker.php', 'laraworker');

        // Enable relative view hashes so compiled view filenames are portable
        // across environments (build-time local paths vs runtime /app paths).
        if (! $this->app['config']->has('view.relative_hash')) {
            $this->app['config']->set('view.relative_hash', true);
        }

        $this->registerCfD1Driver();
    }

    /**
     * Register the Cloudflare D1 database driver.
     *
     * Binds the cfd1 connector so ConnectionFactory can resolve it,
     * and registers the cfd1 connection resolver so Laravel creates
     * CfD1Connection instances (which extend SQLiteConnection).
     */
    protected function registerCfD1Driver(): void
    {
        $this->app->bind('db.connector.cfd1', Database\CfD1Connector::class);

        Connection::resolverFor('cfd1', function ($connection, $database, $prefix, $config) {
            return new Database\CfD1Connection($connection, $database, $prefix, $config);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laraworker.php' => config_path('laraworker.php'),
            ], 'laraworker-config');

            $this->commands([
                InstallCommand::class,
                BuildCommand::class,
                DevCommand::class,
                DeployCommand::class,
                StatusCommand::class,
                DeleteCommand::class,
                MigrateGenerateCommand::class,
            ]);
        }
    }
}
