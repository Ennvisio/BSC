<?php

namespace App\Support\Secrets;

use FFI;
use Throwable;

/**
 * Windows DPAPI, machine scope, called through PHP's FFI extension.
 *
 * The value can be opened only on the Windows installation that protected
 * it: a copy of the secrets folder, a disk image or a backup taken to
 * another PC is useless without this PC. Anyone who can run code on this
 * PC as a user that can read the file can open it, which is why the folder
 * is readable by administrators and the NaviSuite services only (see
 * Set-NsAcl in deploy/windows/lib/NaviSuite.ps1).
 *
 * The same format is written and read by the PowerShell scripts with
 * [Security.Cryptography.ProtectedData] and the same entropy, so the
 * installer can store a secret the application reads and the other way
 * round.
 *
 * Needs php.ini: extension=ffi and ffi.enable=true (the web server is not
 * the CLI, and Windows has no opcache preloading).
 */
class DpapiProtector implements SecretProtector
{
    /** Extra input to DPAPI, so another program's machine-scope blobs are not mistaken for ours. */
    public const ENTROPY = 'NaviSuite secrets v1';

    private const CRYPTPROTECT_UI_FORBIDDEN = 0x1;

    private const CRYPTPROTECT_LOCAL_MACHINE = 0x4;

    private ?FFI $crypt32 = null;

    private ?FFI $kernel32 = null;

    public function name(): string
    {
        return 'dpapi';
    }

    public function available(): bool
    {
        return $this->unavailableReason() === null;
    }

    public function unavailableReason(): ?string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return 'DPAPI exists on Windows only.';
        }
        if (! extension_loaded('ffi')) {
            return 'The PHP FFI extension is not loaded (php.ini: extension=ffi).';
        }
        $enable = strtolower((string) ini_get('ffi.enable'));
        if (! in_array($enable, ['1', 'on', 'true'], true) && ! ($enable === 'preload' && PHP_SAPI === 'cli')) {
            return 'FFI is switched off for this PHP (php.ini: ffi.enable=true).';
        }

        return null;
    }

    public function protect(string $plaintext): string
    {
        return $this->call('CryptProtectData', $plaintext);
    }

    public function unprotect(string $ciphertext): string
    {
        return $this->call('CryptUnprotectData', $ciphertext);
    }

    private function call(string $function, string $input): string
    {
        if (($reason = $this->unavailableReason()) !== null) {
            throw new SecretException($reason);
        }
        $this->load();

        $in = $this->blob($input);
        $entropy = $this->blob(self::ENTROPY);
        $out = $this->crypt32->new('DATA_BLOB');

        try {
            $ok = $this->crypt32->{$function}(
                FFI::addr($in['blob']), null, FFI::addr($entropy['blob']), null, null,
                self::CRYPTPROTECT_UI_FORBIDDEN | self::CRYPTPROTECT_LOCAL_MACHINE, FFI::addr($out)
            );
            if (! $ok) {
                throw new SecretException($function === 'CryptUnprotectData'
                    ? 'Windows could not open the secret. It was protected on another PC or Windows installation, or the file is damaged.'
                    : 'Windows could not protect the secret.');
            }

            return FFI::string($out->pbData, $out->cbData);
        } finally {
            if (! FFI::isNull($out->pbData)) {
                $this->kernel32->LocalFree($out->pbData);
            }
            FFI::free($in['buffer']);
            FFI::free($entropy['buffer']);
        }
    }

    /** @return array{blob: FFI\CData, buffer: FFI\CData} */
    private function blob(string $bytes): array
    {
        $length = strlen($bytes);
        $buffer = FFI::new('uint8_t['.max(1, $length).']', false);
        if ($length > 0) {
            FFI::memcpy($buffer, $bytes, $length);
        }
        $blob = $this->crypt32->new('DATA_BLOB');
        $blob->cbData = $length;
        $blob->pbData = FFI::cast('uint8_t *', $buffer);

        return ['blob' => $blob, 'buffer' => $buffer];
    }

    private function load(): void
    {
        if ($this->crypt32 !== null) {
            return;
        }

        try {
            $this->crypt32 = FFI::cdef(<<<'C'
                typedef struct { uint32_t cbData; uint8_t *pbData; } DATA_BLOB;
                int32_t CryptProtectData(DATA_BLOB *pDataIn, const uint16_t *szDataDescr, DATA_BLOB *pOptionalEntropy,
                    void *pvReserved, void *pPromptStruct, uint32_t dwFlags, DATA_BLOB *pDataOut);
                int32_t CryptUnprotectData(DATA_BLOB *pDataIn, uint16_t **ppszDataDescr, DATA_BLOB *pOptionalEntropy,
                    void *pvReserved, void *pPromptStruct, uint32_t dwFlags, DATA_BLOB *pDataOut);
                C, 'crypt32.dll');
            $this->kernel32 = FFI::cdef('void *LocalFree(void *hMem);', 'kernel32.dll');
        } catch (Throwable $e) {
            $this->crypt32 = null;
            throw new SecretException('Could not load Windows DPAPI: '.$e->getMessage(), 0, $e);
        }
    }
}
