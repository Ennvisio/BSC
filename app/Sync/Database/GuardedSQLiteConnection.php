<?php

namespace App\Sync\Database;

use Illuminate\Database\SQLiteConnection;

class GuardedSQLiteConnection extends SQLiteConnection
{
    use HandsOutGuardedBuilder;
}
