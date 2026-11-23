<?php

namespace App\Sync\Files;

/** Moving a checked file into its place. */
class FileStorage
{
    /**
     * Moves $source to $target, replacing what is there. The target's
     * folder is created; the move is a rename where possible, so a reader
     * never sees half a file.
     */
    public static function place(string $source, string $target): void
    {
        $dir = dirname($target);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new FileTransferException("Cannot create {$dir}.");
        }
        if (! @rename($source, $target)) {
            if (! @copy($source, $target)) {
                throw new FileTransferException("Cannot write {$target}.");
            }
            @unlink($source);
        }
    }
}
