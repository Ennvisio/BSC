<?php

namespace App\Support\Discovery;

use App\Support\AppMode;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * What a discovery answer says about this server. Nothing in it is secret:
 * the same vessel and release are on /health for anyone on the LAN, and the
 * CA certificate is downloadable from /navisuite-ca.crt.
 */
class DiscoveryInfo
{
    public function __construct(private AppMode $mode)
    {
    }

    /**
     * @return array{vessel_id: int, vessel_name: ?string, host: string, https_port: int, http_port: int, ca_sha256: ?string, release: ?string}
     */
    public function __invoke(): array
    {
        $url = (string) config('app.url');
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '' || $host === 'localhost') {
            $host = strtolower((string) gethostname());
        }

        return [
            'vessel_id' => (int) $this->mode->vesselId(),
            'vessel_name' => $this->vesselName(),
            'host' => strtolower($host),
            'https_port' => (int) (parse_url($url, PHP_URL_PORT) ?: 443),
            'http_port' => 80,
            'ca_sha256' => $this->caFingerprint(),
            'release' => config('navisuite.release'),
        ];
    }

    private function vesselName(): ?string
    {
        try {
            $name = DB::table('vessels')->where('id', $this->mode->vesselId())->value('name');

            return is_string($name) ? $name : null;
        } catch (Throwable) {
            return null; // The database being down must not silence discovery.
        }
    }

    private function caFingerprint(): ?string
    {
        $file = config('navisuite.tls.ca_certificate');
        if (! is_string($file) || $file === '' || ! is_readable($file)) {
            return null;
        }

        $pem = @file_get_contents($file, false, null, 0, 65536);

        return is_string($pem) ? DiscoveryProtocol::certificateFingerprint($pem) : null;
    }
}
