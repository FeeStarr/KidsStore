<?php

namespace App\Database;

use Illuminate\Database\MySqlConnection;

/**
 * A MySQL connection that refuses destructive queries (TRUNCATE, DROP,
 * DELETE without a WHERE clause) unless the app is running in a test
 * environment. This protects live data from accidental mass deletion.
 */
class SafeMysqlConnection extends MySqlConnection
{
    protected function run($query, $bindings, $callback)
    {
        $this->guardAgainstDestructiveQuery($query);

        return parent::run($query, $bindings, $callback);
    }

    /**
     * Throw before execution if the statement is destructive and we are not
     * running tests.
     */
    protected function guardAgainstDestructiveQuery(string $query): void
    {
        if (app()->runningUnitTests() || $this->isTestDatabase()) {
            return;
        }

        // Deliberate destructive operations can be allowed via env flag.
        if (filter_var(env('DATA_SAFETY_ALLOW_DESTRUCTIVE', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $query)));

        // TRUNCATE TABLE x - wipes every row and resets auto-increment.
        if (preg_match('/^truncate\s+table?\s+/', $normalized)) {
            throw new \RuntimeException(
                'Blocked destructive query: TRUNCATE is not allowed outside test environments.'
            );
        }

        // DROP TABLE / DROP DATABASE
        if (preg_match('/^drop\s+(table\s+)?(if\s+exists\s+)?[`"\w.]+/i', $normalized)) {
            throw new \RuntimeException(
                'Blocked destructive query: DROP is not allowed outside test environments.'
            );
        }

        // DELETE FROM t without any WHERE clause
        if (preg_match('/^delete\s+from\s+[`"\w.]+$/i', $normalized)
            || preg_match('/^delete\s+from\s+[`"\w.]+\s*;?\s*$/i', $normalized)) {
            throw new \RuntimeException(
                'Blocked destructive query: DELETE without a WHERE clause is not allowed outside test environments.'
            );
        }
    }

    protected function isTestDatabase(): bool
    {
        $database = $this->getDatabaseName();

        if (! $database) {
            return false;
        }

        return str_contains($database, 'test');
    }
}
