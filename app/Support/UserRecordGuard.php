<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PD-21 / PD-74 (owner's conclusion, 2026-11-24): a requisition shows each
 * signer's signature from their own user record, so a user who raised or
 * signed a requisition, and their signature file, must stay. The
 * application has no way to delete a user ("delete" blocks the account)
 * and never deletes a signature file; this keeps it so for any other path
 * (a model delete, a query-builder delete, a delete arriving by sync).
 */
class UserRecordGuard
{
    /** The columns that name a requisition's raiser or a signer. */
    public const REFERENCES = [
        'orders' => ['created_by'],
        'order_approvals' => [
            'cheif_ofcr_app', 'second_eng_app', 'master_app', 'chief_eng_app', 'dgm_srd_app', 'agm_app', 'ast_m_app',
            'superintendent_srd_app', 'gm_app', 'dgm_app_ssm', 'agm_app_ssm', 'am_app_ssm', 'superintendent_ssm_app',
            'tech_superintendent_app', 'marine_superintendent_app',
        ],
        'service_requisitions' => ['created_by'],
        'service_requisition_approvals' => [
            'cheif_ofcr_app', 'second_eng_app', 'master_app', 'chief_eng_app', 'dgm_srd_app', 'agm_app', 'ast_m_app',
            'superintendent_srd_app', 'gm_app',
        ],
    ];

    /** @return list<string> the tables whose requisition records name this user */
    public static function requisitionsReferring(int $userId): array
    {
        $found = [];
        foreach (self::REFERENCES as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $present = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c)));
            if ($present === []) {
                continue;
            }
            $hit = DB::table($table)->where(function ($q) use ($present, $userId) {
                foreach ($present as $column) {
                    $q->orWhere($column, $userId);
                }
            })->exists();
            if ($hit) {
                $found[] = $table;
            }
        }

        return $found;
    }

    public static function refusal(int $userId): ?string
    {
        $tables = self::requisitionsReferring($userId);

        return $tables === [] ? null
            : 'This user raised or signed requisitions ('.implode(', ', array_map(fn ($t) => str_replace('_', ' ', $t), $tables))
              .'), which show their name and signature, so the user is kept. Block the account instead. Nothing was deleted.';
    }

    public static function assertMayDelete(int $userId): void
    {
        if (($why = self::refusal($userId)) !== null) {
            throw new UserStillReferenced($why);
        }
    }
}
