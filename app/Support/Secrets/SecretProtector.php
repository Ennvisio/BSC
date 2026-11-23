<?php

namespace App\Support\Secrets;

/**
 * Encrypts a secret before it is written to disk. See SecretStore.
 */
interface SecretProtector
{
    /** Short name stored with each secret, so the right protector opens it. */
    public function name(): string;

    /** Whether this protector can work on this server (operating system, PHP extensions). */
    public function available(): bool;

    /** Why it is not available, for the status command. */
    public function unavailableReason(): ?string;

    public function protect(string $plaintext): string;

    /** @throws SecretException the value cannot be opened here (another PC, a damaged file, a wrong key) */
    public function unprotect(string $ciphertext): string;
}
