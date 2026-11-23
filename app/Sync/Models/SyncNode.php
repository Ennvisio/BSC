<?php

namespace App\Sync\Models;

use Illuminate\Database\Eloquent\Model;

/** A registered server: the cloud or one vessel. See App\Sync\SyncNodes. */
class SyncNode extends Model
{
    public const KIND_CLOUD = 'cloud';

    public const KIND_VESSEL = 'vessel';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $table = 'sync_nodes';

    protected $fillable = ['node_id', 'kind', 'vessel_id', 'name', 'token_hash', 'status', 'app_version', 'schema_version', 'last_seen_at', 'revoked_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['vessel_id' => 'integer', 'last_seen_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
