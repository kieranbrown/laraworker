<?php

namespace Tests;

use Laraworker\LaraworkerServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaraworkerServiceProvider::class,
        ];
    }
}
