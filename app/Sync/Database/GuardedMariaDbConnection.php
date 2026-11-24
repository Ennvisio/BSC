<?php

namespace App\Sync\Database;

use Illuminate\Database\MariaDbConnection;

class GuardedMariaDbConnection extends MariaDbConnection
{
    use HandsOutGuardedBuilder;
}
