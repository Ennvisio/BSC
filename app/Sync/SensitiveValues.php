<?php

namespace App\Sync;

/**
 * Columns whose values sync bookkeeping never keeps (config/sync.php
 * 'sensitive_columns'): password hashes and "remember me" tokens. They
 * travel when they must (a crew member's new password), but the copies
 * the sync keeps for operators (conflicts, applied inbox entries) show
 * only that there was a value.
 */
class SensitiveValues
{
    public const MASK = '[hidden]';

    /** @return list<string> */
    public static function columns(string $table): array
    {
        return array_values((array) config("sync.sensitive_columns.{$table}", []));
    }

    public static function mask(string $table, ?array $values): ?array
    {
        if ($values === null) {
            return null;
        }
        foreach (self::columns($table) as $column) {
            if (array_key_exists($column, $values) && $values[$column] !== null) {
                $values[$column] = self::MASK;
            }
        }

        return $values;
    }
}
