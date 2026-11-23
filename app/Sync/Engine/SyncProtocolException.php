<?php

namespace App\Sync\Engine;

use RuntimeException;

/**
 * The other side broke the protocol, or the two servers disagree about who
 * they are. Not fixed by retrying soon: an operator has to look.
 */
class SyncProtocolException extends RuntimeException
{
}
