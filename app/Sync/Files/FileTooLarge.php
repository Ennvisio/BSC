<?php

namespace App\Sync\Files;

/**
 * PD-22: a file larger than the servers exchange (sync.files.max_file_bytes).
 * It can never travel, so it is marked failed at once instead of retried.
 */
class FileTooLarge extends FileTransferException
{
}
