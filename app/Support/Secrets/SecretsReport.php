<?php

namespace App\Support\Secrets;

use App\Providers\SecretsServiceProvider;
use App\Support\AppMode;

/**
 * What navisuite:status, navisuite:secrets status and /health say about the
 * credentials: where each comes from and what is wrong.
 */
class SecretsReport
{
    public function __construct(private AppMode $mode, private SecretStore $store)
    {
    }

    /** @return list<array{name: string, setting: string, env: string, source: string, error: ?string}> */
    public function rows(): array
    {
        $rows = [];
        foreach ((array) config('navisuite.secrets.map', []) as $name => $target) {
            $source = SecretsServiceProvider::$sources[$name] ?? ['source' => 'unknown'];
            $rows[] = [
                'name' => (string) $name,
                'setting' => (string) ($target['config'] ?? ''),
                'env' => (string) ($target['env'] ?? ''),
                'source' => $source['source'],
                'error' => $source['error'] ?? null,
            ];
        }

        return $rows;
    }

    /** @return array{errors: list<string>, warnings: list<string>} */
    public function problems(): array
    {
        $errors = [];
        $warnings = [];
        $required = $this->mode->isVessel() ? (array) config('navisuite.secrets.required_on_vessel', []) : [];

        foreach ($this->rows() as $row) {
            if ($row['source'] === 'error') {
                $errors[] = "Secret {$row['name']} cannot be opened: {$row['error']}";
            } elseif ($row['source'] === 'env' && in_array($row['name'], $required, true)) {
                $warnings[] = "{$row['env']} is stored in plaintext in .env. Move it with \"php artisan navisuite:secrets import-env\".";
            }
        }
        if (! $this->store->protector()->available()) {
            $warnings[] = 'New secrets cannot be stored: '.$this->store->protector()->unavailableReason();
        } elseif ($this->mode->isVessel() && $this->store->protector()->name() !== 'dpapi' && PHP_OS_FAMILY === 'Windows') {
            $warnings[] = 'Secrets on this vessel server are protected with a key file, not Windows DPAPI. Enable FFI in php.ini (extension=ffi, ffi.enable=true).';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }
}
