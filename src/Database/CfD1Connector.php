<?php

namespace Laraworker\Database;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;
use PDO;

/**
 * PDO connector for Cloudflare D1 databases via the pdo-cfd1 extension.
 *
 * Creates a PDO connection using the `cfd1:` DSN scheme. The pdo-cfd1
 * extension (compiled into the PHP WASM binary) bridges PHP's PDO API
 * to D1's JavaScript binding via Emscripten EM_ASM interop.
 *
 * DSN format: `cfd1:<binding_name>` where binding_name matches the
 * D1 binding declared in wrangler.jsonc (e.g., `cfd1:DB`).
 *
 * @see https://github.com/nickmccurdy/pdo-cfd1
 */
class CfD1Connector extends Connector implements ConnectorInterface
{
    /**
     * Establish a database connection to a Cloudflare D1 database.
     *
     * @param  array{d1_binding?: string, options?: array<int, mixed>}  $config
     */
    public function connect(array $config): PDO
    {
        $dsn = $this->getDsn($config);

        $options = $this->getOptions($config);

        return $this->createConnection($dsn, $config, $options);
    }

    /**
     * Build the pdo-cfd1 DSN string from the config.
     *
     * @param  array{d1_binding?: string}  $config
     */
    protected function getDsn(array $config): string
    {
        $binding = $config['d1_binding'] ?? 'DB';

        return "cfd1:{$binding}";
    }
}
