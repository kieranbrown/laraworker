<?php

namespace Laraworker\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;

class MigrateGenerateCommand extends Command
{
    protected $signature = 'laraworker:migrate:generate
                            {--output-dir= : Directory to write SQL files (defaults to database/d1-migrations)}
                            {--fresh : Regenerate all SQL files from scratch}';

    protected $description = 'Generate D1 SQL migration files from Laravel migrations';

    public function handle(): int
    {
        $d1Databases = config('laraworker.d1_databases', []);

        if (empty($d1Databases)) {
            $this->components->error('No D1 databases configured in config/laraworker.php.');

            return self::FAILURE;
        }

        $outputDir = $this->option('output-dir') ?? database_path('d1-migrations');

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        if ($this->option('fresh')) {
            foreach (glob($outputDir.'/*.sql') ?: [] as $file) {
                unlink($file);
            }
        }

        $tempDb = sys_get_temp_dir().'/laraworker-d1-migrations.sqlite';

        if ($this->option('fresh') && file_exists($tempDb)) {
            unlink($tempDb);
        }

        if (! file_exists($tempDb)) {
            touch($tempDb);
        }

        config(['database.connections.d1_migrate_temp' => [
            'driver' => 'sqlite',
            'database' => $tempDb,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        DB::purge('d1_migrate_temp');

        /** @var Migrator $migrator */
        $migrator = app('migrator');
        $allPaths = array_merge($migrator->paths(), [database_path('migrations')]);
        $files = array_values($migrator->getMigrationFiles($allPaths));

        if (empty($files)) {
            $this->components->warn('No migration files found.');

            return self::SUCCESS;
        }

        // Build a map of existing SQL files: migration_name => sequential_number.
        // Wrangler sorts D1 migrations by parsing the first segment of the filename
        // as an integer. Files must be named like `0001_migration_name.sql`.
        /** @var array<string, int> $existingMap */
        $existingMap = [];
        foreach (glob($outputDir.'/*.sql') ?: [] as $existing) {
            $base = basename($existing, '.sql');
            if (preg_match('/^(\d+)_(.+)$/', $base, $m)) {
                $existingMap[$m[2]] = (int) $m[1];
            }
        }

        $nextNumber = empty($existingMap) ? 1 : max($existingMap) + 1;

        $generated = 0;
        $skipped = 0;
        $originalDefault = config('database.default');
        config(['database.default' => 'd1_migrate_temp']);

        try {
            $connection = DB::connection('d1_migrate_temp');

            foreach ($files as $index => $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $alreadyGenerated = isset($existingMap[$name]);

                $migration = $this->resolveMigration($file);

                if (! $migration) {
                    $this->components->warn("Could not resolve migration class for: {$name}");

                    continue;
                }

                // Run the migration on the temp SQLite DB, capturing the query log.
                // Using enableQueryLog() (rather than pretend()) is necessary for
                // migrations that use ->change() on SQLite: pretend() intercepts
                // the PRAGMA introspection queries so they return empty results,
                // causing recreated tables to have no columns. With the query log,
                // introspection PRACMAs execute normally and the DDL is fully formed.
                $connection->enableQueryLog();
                $connection->flushQueryLog();

                try {
                    $migration->up();
                } catch (\Throwable) {
                    // Table already exists on incremental run — safe to ignore
                }

                $queryLog = $connection->getQueryLog();
                $connection->disableQueryLog();
                $connection->flushQueryLog();

                if ($alreadyGenerated) {
                    $skipped++;

                    continue;
                }

                // Filter out SQLite introspection queries: PRAGMA directives and
                // SELECT queries from sqlite_master / pragma_table_xinfo are used
                // by Laravel's SQLite grammar during column changes, but D1 doesn't
                // need them (it processes the resulting DDL statements directly).
                $ddlQueries = array_values(array_filter(
                    $queryLog,
                    fn (array $q): bool => ! $this->isIntrospectionQuery($q['query'])
                ));

                // Use sequential number as prefix — wrangler sorts by int(name.split('_')[0])
                $seqNumber = $this->option('fresh') ? ($index + 1) : $nextNumber++;
                $prefix = str_pad($seqNumber, 4, '0', STR_PAD_LEFT);
                $sqlFile = $outputDir.'/'.$prefix.'_'.$name.'.sql';

                $sqlLines = [];
                foreach ($ddlQueries as $query) {
                    $sqlLines[] = $this->interpolateQuery($query['query'], $query['bindings']).';';
                }

                file_put_contents($sqlFile, implode("\n", $sqlLines)."\n");
                $this->components->twoColumnDetail($name, '<fg=green>generated</>');
                $generated++;
            }
        } finally {
            config(['database.default' => $originalDefault]);
            DB::purge('d1_migrate_temp');
        }

        $this->newLine();
        $this->components->info("Generated {$generated} SQL file(s), skipped {$skipped} existing.");

        return self::SUCCESS;
    }

    /**
     * Return true for SQLite introspection queries that should not appear in D1 migrations.
     *
     * Laravel's SQLite grammar uses PRAGMA directives and SELECT queries against
     * sqlite_master / pragma_table_xinfo to introspect existing tables when
     * performing column changes. These queries are specific to the local temp DB
     * and must not be included in the D1 migration files.
     */
    private function isIntrospectionQuery(string $query): bool
    {
        $sql = strtolower(ltrim($query));

        return str_starts_with($sql, 'pragma')
            || str_starts_with($sql, 'select');
    }

    /**
     * Require a migration file and return the migration instance.
     *
     * Handles both anonymous class migrations (`return new class extends Migration {...}`)
     * and named class migrations (older style).
     */
    private function resolveMigration(string $file): ?object
    {
        $classesBefore = get_declared_classes();

        $result = require $file;

        if (is_object($result)) {
            return $result;
        }

        $classesAfter = get_declared_classes();
        $new = array_diff($classesAfter, $classesBefore);

        if (empty($new)) {
            return null;
        }

        $class = end($new);

        return new $class;
    }

    /**
     * Interpolate PDO bindings into a query string, replacing `?` placeholders.
     *
     * @param  array<int, mixed>  $bindings
     */
    private function interpolateQuery(string $query, array $bindings): string
    {
        if (empty($bindings)) {
            return $query;
        }

        $pdo = DB::connection('d1_migrate_temp')->getPdo();

        foreach ($bindings as $binding) {
            $value = match (true) {
                is_null($binding) => 'NULL',
                is_bool($binding) => $binding ? '1' : '0',
                is_int($binding), is_float($binding) => (string) $binding,
                default => $pdo->quote((string) $binding),
            };

            $query = preg_replace('/\?/', $value, $query, 1);
        }

        return $query;
    }
}
