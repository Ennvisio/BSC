<?php

namespace App\Sync\Database;

use Illuminate\Database\MySqlConnection;

class GuardedMySqlConnection extends MySqlConnection
{
    use HandsOutGuardedBuilder;
}
