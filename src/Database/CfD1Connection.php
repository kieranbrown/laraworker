<?php

namespace Laraworker\Database;

use Illuminate\Database\SQLiteConnection;

/**
 * Database connection for Cloudflare D1 via pdo-cfd1.
 *
 * D1 is SQLite-compatible, so this extends SQLiteConnection to reuse
 * the SQLite query grammar, schema grammar, and query processor.
 *
 * Known limitations:
 * - Only positional (`?`) parameters are supported (not named `:param`).
 *   Laravel's query builder uses `?` by default, so this is transparent.
 * - Transaction support in pdo-cfd1 is limited (beginTransaction/commit
 *   return hardcoded true). D1 itself supports transactions.
 */
class CfD1Connection extends SQLiteConnection
{
    public function getDriverTitle(): string
    {
        return 'Cloudflare D1';
    }
}
