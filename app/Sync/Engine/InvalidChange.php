<?php

namespace App\Sync\Engine;

use RuntimeException;

/** A received change that can never be applied as sent: malformed, or outside what the sender may change. */
class InvalidChange extends RuntimeException
{
}
