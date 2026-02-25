<?php

use Illuminate\Database\Connection;
use Illuminate\Database\SQLiteConnection;
use Laraworker\Database\CfD1Connection;
use Laraworker\Database\CfD1Connector;

test('CfD1Connector builds correct DSN with default binding', function () {
    $connector = new CfD1Connector;

    $dsn = (new ReflectionMethod($connector, 'getDsn'))->invoke($connector, []);

    expect($dsn)->toBe('cfd1:DB');
});

test('CfD1Connector builds correct DSN with custom binding', function () {
    $connector = new CfD1Connector;

    $dsn = (new ReflectionMethod($connector, 'getDsn'))->invoke($connector, [
        'd1_binding' => 'ANALYTICS',
    ]);

    expect($dsn)->toBe('cfd1:ANALYTICS');
});

test('CfD1Connector implements ConnectorInterface', function () {
    $connector = new CfD1Connector;

    expect($connector)->toBeInstanceOf(
        Illuminate\Database\Connectors\ConnectorInterface::class
    );
});

test('CfD1Connector extends base Connector', function () {
    $connector = new CfD1Connector;

    expect($connector)->toBeInstanceOf(
        Illuminate\Database\Connectors\Connector::class
    );
});

test('CfD1Connection extends SQLiteConnection', function () {
    // CfD1Connection needs a PDO-like object. Use a closure (lazy connection).
    $connection = new CfD1Connection(
        fn () => new PDO('sqlite::memory:'),
        ':memory:',
        '',
        ['driver' => 'cfd1'],
    );

    expect($connection)->toBeInstanceOf(SQLiteConnection::class);
});

test('CfD1Connection driver title is Cloudflare D1', function () {
    $connection = new CfD1Connection(
        fn () => new PDO('sqlite::memory:'),
        ':memory:',
        '',
        ['driver' => 'cfd1'],
    );

    expect($connection->getDriverTitle())->toBe('Cloudflare D1');
});

test('CfD1Connection uses SQLite query grammar', function () {
    $connection = new CfD1Connection(
        fn () => new PDO('sqlite::memory:'),
        ':memory:',
        '',
        ['driver' => 'cfd1'],
    );

    expect($connection->getQueryGrammar())
        ->toBeInstanceOf(Illuminate\Database\Query\Grammars\SQLiteGrammar::class);
});

test('CfD1Connection uses SQLite schema grammar', function () {
    $connection = new CfD1Connection(
        fn () => new PDO('sqlite::memory:'),
        ':memory:',
        '',
        ['driver' => 'cfd1'],
    );

    // Schema grammar is lazily initialized via the schema builder
    expect($connection->getSchemaBuilder()->getConnection())
        ->toBeInstanceOf(CfD1Connection::class);

    expect($connection->getSchemaGrammar())
        ->toBeInstanceOf(Illuminate\Database\Schema\Grammars\SQLiteGrammar::class);
});

test('CfD1Connection uses SQLite post processor', function () {
    $connection = new CfD1Connection(
        fn () => new PDO('sqlite::memory:'),
        ':memory:',
        '',
        ['driver' => 'cfd1'],
    );

    expect($connection->getPostProcessor())
        ->toBeInstanceOf(Illuminate\Database\Query\Processors\SQLiteProcessor::class);
});

test('cfd1 driver is registered via Connection resolver', function () {
    $resolver = Connection::getResolver('cfd1');

    expect($resolver)->not->toBeNull();
});

test('cfd1 resolver creates CfD1Connection instance', function () {
    $resolver = Connection::getResolver('cfd1');
    $pdo = fn () => new PDO('sqlite::memory:');

    $connection = $resolver($pdo, ':memory:', '', ['driver' => 'cfd1']);

    expect($connection)->toBeInstanceOf(CfD1Connection::class);
});

test('cfd1 connector is bound in container', function () {
    expect(app()->bound('db.connector.cfd1'))->toBeTrue();
});

test('cfd1 connector resolves to CfD1Connector', function () {
    $connector = app('db.connector.cfd1');

    expect($connector)->toBeInstanceOf(CfD1Connector::class);
});
