<?php

use Laraworker\LaraworkerServiceProvider;

test('service provider is registered', function () {
    expect($this->app->getProviders(LaraworkerServiceProvider::class))
        ->not->toBeEmpty();
});
