<?php

namespace Laraworker;

use Illuminate\Support\ServiceProvider;
use Laraworker\Console\BuildCommand;
use Laraworker\Console\DeployCommand;
use Laraworker\Console\DevCommand;
use Laraworker\Console\InstallCommand;
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
            ]);
        }
    }
}
