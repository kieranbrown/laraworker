<?php

namespace Laraworker;

use Illuminate\Support\ServiceProvider;
use Laraworker\Console\BuildCommand;
use Laraworker\Console\DeployCommand;
use Laraworker\Console\DevCommand;
use Laraworker\Console\InstallCommand;

class LaraworkerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laraworker.php', 'laraworker');
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
            ]);
        }
    }
}
