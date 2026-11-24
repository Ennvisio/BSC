<?php

namespace App\Support;

use App\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which deployment this server is - the shore cloud or one vessel's onboard
 * server - and the rules that follow from it. Every mode decision reads from
 * here, so config/navisuite.php is only ever interpreted in one place.
 *
 * It reads config() on every call, so a config change (or a test calling
 * config([...])) takes effect immediately.
 */
class AppMode
{
    public const CLOUD = 'cloud';

    public const VESSEL = 'vessel';

    public const MODES = [self::CLOUD, self::VESSEL];

    /** Memo for hasOfflineColumn(): true once seen, null until then. */
    private ?bool $hasOfflineColumn = null;

    /** The configured mode, normalised. A blank value means cloud. */
    public function mode(): string
    {
        $mode = strtolower(trim((string) config('navisuite.mode', self::CLOUD)));

        return $mode === '' ? self::CLOUD : $mode;
    }

    public function isCloud(): bool
    {
        return $this->mode() === self::CLOUD;
    }

    public function isVessel(): bool
    {
        return $this->mode() === self::VESSEL;
    }

    /** The configured vessel id when it is a positive whole number, otherwise null. */
    public function vesselId(): ?int
    {
        $raw = trim((string) config('navisuite.vessel_id', ''));

        return ctype_digit($raw) && (int) $raw > 0 ? (int) $raw : null;
    }

    /** @return list<string> lower-cased e-mail addresses */
    public function supportAccounts(): array
    {
        $raw = config('navisuite.support_accounts', '');
        $list = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_filter(array_map(
            fn ($email) => strtolower(trim((string) $email)),
            $list
        )));
    }

    /**
     * Problems that make it unsafe to serve requests. Only configuration is
     * checked here, never the database, because this runs on every request.
     * The navisuite:status command adds the database checks.
     *
     * @return list<string>
     */
    public function configurationErrors(): array
    {
        $errors = [];

        if (! in_array($this->mode(), self::MODES, true)) {
            $errors[] = sprintf(
                'APP_MODE is "%s". It must be "%s" or "%s".',
                $this->mode(), self::CLOUD, self::VESSEL
            );

            return $errors;
        }

        if ($this->isVessel() && $this->vesselId() === null) {
            $errors[] = 'APP_MODE is "vessel" but APP_VESSEL_ID is missing or not a positive whole number.';
        }

        return $errors;
    }

    public function isConfigured(): bool
    {
        return $this->configurationErrors() === [];
    }

    public function isSupportAccount(User $user): bool
    {
        return in_array(strtolower((string) $user->email), $this->supportAccounts(), true);
    }

    /**
     * Whether this user may sign in to this server. In cloud mode everyone
     * may, as before. In vessel mode only this vessel's ship crew and the
     * listed support accounts may. Shore users exist on a vessel server only
     * so their names and signatures can be shown on paperwork.
     */
    public function userMayUseThisServer(User $user): bool
    {
        if (! $this->isVessel()) {
            return true;
        }

        if ($this->isSupportAccount($user)) {
            return true;
        }

        $role = $user->role;

        return $role !== null
            && $role->user_type === 'ship'
            && $this->vesselId() !== null
            && (int) $role->vessel_id === $this->vesselId();
    }

    /**
     * Whether the vessel has been switched to its own onboard server, so the
     * cloud holds a read-only copy of its crew's work.
     *
     * A database without the offline_enabled_at column (the migration not yet
     * run, or a partial test schema) is treated as "not offline", which is
     * exactly the behaviour before this feature existed.
     */
    /**
     * The vessels marked as running their own server (PD-34 checks bulk
     * writes against them). Empty before the column exists.
     *
     * @return list<int>
     */
    public function offlineEnabledVesselIds(): array
    {
        try {
            if (! $this->hasOfflineColumn()) {
                return [];
            }

            return DB::table('vessels')->whereNotNull('offline_enabled_at')->pluck('id')->map(fn ($id) => (int) $id)->all();
        } catch (QueryException) {
            return [];
        }
    }

    public function vesselIsOfflineEnabled(int $vesselId): bool
    {
        try {
            if (! $this->hasOfflineColumn()) {
                return false;
            }

            return DB::table('vessels')->where('id', $vesselId)->value('offline_enabled_at') !== null;
        } catch (QueryException) {
            return false;
        }
    }

    /**
     * Checked explicitly rather than by catching the error from a missing
     * column: SQLite reads an unknown double-quoted name as a text literal,
     * which would look like a non-null date. Only a positive answer is
     * remembered, so a migration run while a process is alive is noticed.
     */
    private function hasOfflineColumn(): bool
    {
        if ($this->hasOfflineColumn !== true && Schema::hasColumn('vessels', 'offline_enabled_at')) {
            $this->hasOfflineColumn = true;
        }

        return $this->hasOfflineColumn === true;
    }
}
