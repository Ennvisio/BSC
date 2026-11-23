<?php

namespace App\Support\Secrets;

/**
 * Libsodium secretbox with a key file, for servers that are not Windows
 * (the cloud on Linux, developers' machines).
 *
 * This keeps secrets out of .env, the configuration cache, logs and
 * anything copied with the application, but the key file sits on the same
 * server: whoever can read both can open the secrets. Keep the key file
 * readable by the application's user only, and outside backups that leave
 * the server. Where the platform has a key service (Windows DPAPI here), it
 * is preferred.
 */
class SodiumProtector implements SecretProtector
{
    public function __construct(private string $keyFile)
    {
    }

    public function name(): string
    {
        return 'sodium';
    }

    public function available(): bool
    {
        return $this->unavailableReason() === null;
    }

    public function unavailableReason(): ?string
    {
        return function_exists('sodium_crypto_secretbox') ? null : 'The PHP sodium extension is not loaded.';
    }

    public function protect(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return $nonce.sodium_crypto_secretbox($plaintext, $nonce, $this->key(true));
    }

    public function unprotect(string $ciphertext): string
    {
        if (strlen($ciphertext) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new SecretException('The secret is damaged.');
        }
        $nonce = substr($ciphertext, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($ciphertext, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key(false));
        if ($plain === false) {
            throw new SecretException('The secret could not be opened: wrong key file or a damaged secret.');
        }

        return $plain;
    }

    public function keyFile(): string
    {
        return $this->keyFile;
    }

    private function key(bool $create): string
    {
        if (! is_file($this->keyFile)) {
            if (! $create) {
                throw new SecretException("The key file {$this->keyFile} is missing, so no secret can be opened.");
            }
            SecretStore::writePrivateFile($this->keyFile, base64_encode(sodium_crypto_secretbox_keygen())."\n");
        }

        $key = base64_decode(trim((string) file_get_contents($this->keyFile)), true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new SecretException("The key file {$this->keyFile} is not a valid key.");
        }

        return $key;
    }
}
