<?php

namespace App\Support\Secrets;

/**
 * Credentials kept encrypted on disk instead of in .env (Checkpoint 7).
 *
 * One file per secret, {path}/{name}.secret, holding
 *
 *     NSSEC1:<protector>:<base64 of the protected value>
 *
 * The protector is Windows DPAPI on the vessel servers and a libsodium key
 * file elsewhere (config/navisuite.php 'secrets'). Values are opened when
 * first needed and kept in memory for the rest of the request only; they
 * are never written to the configuration cache (SecretsServiceProvider).
 */
class SecretStore
{
    public const PREFIX = 'NSSEC1';

    /** @var array<string, string> */
    private array $opened = [];

    /** @param array<string, SecretProtector> $protectors by name */
    public function __construct(
        private string $path,
        private SecretProtector $writer,
        private array $protectors,
    ) {
    }

    public static function fromConfig(): self
    {
        $path = rtrim((string) (config('navisuite.secrets.path') ?: storage_path('app/secrets')), '/\\');
        $dpapi = new DpapiProtector;
        $sodium = new SodiumProtector($path.DIRECTORY_SEPARATOR.'secrets.key');

        $writer = match (strtolower((string) config('navisuite.secrets.protector', 'auto'))) {
            'dpapi' => $dpapi,
            'sodium' => $sodium,
            default => $dpapi->available() ? $dpapi : $sodium,
        };

        return new self($path, $writer, ['dpapi' => $dpapi, 'sodium' => $sodium]);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function protector(): SecretProtector
    {
        return $this->writer;
    }

    public function has(string $name): bool
    {
        return is_file($this->file($name));
    }

    /** @throws SecretException when the secret exists but cannot be opened */
    public function get(string $name): ?string
    {
        if (array_key_exists($name, $this->opened)) {
            return $this->opened[$name];
        }
        $file = $this->file($name);
        if (! is_file($file)) {
            return null;
        }

        $content = trim((string) @file_get_contents($file));
        $parts = explode(':', $content, 3);
        if (count($parts) !== 3 || $parts[0] !== self::PREFIX) {
            throw new SecretException("The secret \"{$name}\" is not in the expected format.");
        }
        $protector = $this->protectors[$parts[1]] ?? null;
        if ($protector === null) {
            throw new SecretException("The secret \"{$name}\" was protected with \"{$parts[1]}\", which this server does not know.");
        }
        $blob = base64_decode($parts[2], true);
        if ($blob === false) {
            throw new SecretException("The secret \"{$name}\" is damaged.");
        }

        return $this->opened[$name] = $protector->unprotect($blob);
    }

    public function put(string $name, string $value): void
    {
        if ($value === '') {
            throw new SecretException('An empty value is not a secret. Use forget to remove one.');
        }
        if (! $this->writer->available()) {
            throw new SecretException('Secrets cannot be protected here: '.$this->writer->unavailableReason());
        }

        $line = self::PREFIX.':'.$this->writer->name().':'.base64_encode($this->writer->protect($value))."\n";
        self::writePrivateFile($this->file($name), $line);
        $this->opened[$name] = $value;
    }

    public function forget(string $name): bool
    {
        unset($this->opened[$name]);
        $file = $this->file($name);

        return is_file($file) && @unlink($file);
    }

    /** @return list<string> */
    public function names(): array
    {
        $names = [];
        foreach (glob($this->path.DIRECTORY_SEPARATOR.'*.secret') ?: [] as $file) {
            $names[] = basename($file, '.secret');
        }
        sort($names);

        return $names;
    }

    /** The protector a stored secret uses, without opening it. */
    public function protectorOf(string $name): ?string
    {
        $content = @file_get_contents($this->file($name));

        return $content === false ? null : (explode(':', $content, 3)[1] ?? null);
    }

    public static function validName(string $name): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]{1,63}$/', $name);
    }

    /**
     * Written to a temporary file next to it and renamed, so a reader never
     * sees half a file, with owner-only permissions where the OS has them
     * (Windows relies on the folder's ACL, set by the installer).
     */
    public static function writePrivateFile(string $file, string $content): void
    {
        $dir = dirname($file);
        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new SecretException("Cannot create the secrets folder {$dir}.");
        }
        $tmp = $file.'.'.bin2hex(random_bytes(6)).'.tmp';
        $old = umask(0077);
        try {
            if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
                throw new SecretException("Cannot write {$file}.");
            }
            @chmod($tmp, 0600);
            if (! @rename($tmp, $file)) {
                @unlink($file);
                if (! @rename($tmp, $file)) {
                    throw new SecretException("Cannot write {$file}.");
                }
            }
        } finally {
            umask($old);
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    private function file(string $name): string
    {
        if (! self::validName($name)) {
            throw new SecretException("\"{$name}\" is not a valid secret name (lower-case letters, digits and _).");
        }

        return $this->path.DIRECTORY_SEPARATOR.$name.'.secret';
    }
}
