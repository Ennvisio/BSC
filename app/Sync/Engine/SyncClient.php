<?php

namespace App\Sync\Engine;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * HTTPS calls from a vessel to the cloud's /api/sync/v1.
 *
 * Every request carries the vessel's node id and secret token. Request
 * bodies are gzip-compressed (satellite links are slow and metered) with a
 * SHA-256 of the uncompressed JSON, so a damaged body is refused rather
 * than half-applied.
 */
class SyncClient
{
    public function __construct(
        private string $baseUrl,
        private string $nodeId,
        private string $token,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            rtrim((string) config('sync.cloud_url'), '/').'/api/sync/v1',
            (string) config('sync.node_id'),
            (string) config('sync.node_token'),
        );
    }

    public function post(string $path, array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $this->send(fn ($http) => $http
            ->withHeaders(['Content-Encoding' => 'gzip', 'X-Sync-Body-SHA256' => hash('sha256', $json)])
            ->withBody(gzencode($json, 6), 'application/json')
            ->post($this->baseUrl.$path));
    }

    public function get(string $path, array $query = []): array
    {
        return $this->send(fn ($http) => $http->get($this->baseUrl.$path, $query));
    }

    /**
     * Raw request for the file channel (Checkpoint 7): the answer's status,
     * body and headers, whatever the status. Only a network failure throws.
     *
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    public function raw(string $method, string $path, array $query = [], ?string $body = null, array $headers = []): array
    {
        try {
            $http = $this->http()->withHeaders($headers);
            $url = $this->baseUrl.$path.($query === [] ? '' : '?'.http_build_query($query));
            /** @var Response $response */
            $response = $body === null
                ? $http->send($method, $url)
                : $http->withBody($body, 'application/octet-stream')->send($method, $url);
        } catch (ConnectionException $e) {
            throw new SyncTransportException('Cloud not reachable: '.$e->getMessage(), null, $e);
        }

        return [
            'status' => $response->status(),
            'body' => $response->body(),
            'headers' => array_map(fn ($v) => is_array($v) ? implode(', ', $v) : (string) $v, array_change_key_case($response->headers(), CASE_LOWER)),
        ];
    }

    private function http()
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Sync-Node' => $this->nodeId,
            'X-Sync-Protocol' => (string) SyncServer::PROTOCOL,
            'Accept' => 'application/json',
            'Accept-Encoding' => 'gzip',
        ])
            ->connectTimeout((int) config('sync.http.connect_timeout', 20))
            ->timeout((int) config('sync.http.timeout', 120))
            ->withOptions(['verify' => config('sync.http.ca_bundle') ?: true]);
    }

    private function send(callable $call): array
    {
        $http = $this->http();

        try {
            /** @var Response $response */
            $response = $call($http);
        } catch (ConnectionException $e) {
            throw new SyncTransportException('Cloud not reachable: '.$e->getMessage(), null, $e);
        }

        $body = $response->body();
        if (str_starts_with($body, "\x1f\x8b")) {
            $body = gzdecode($body, (int) config('sync.http.max_request_bytes')) ?: '';
        }
        $data = json_decode($body, true);

        if (! $response->successful()) {
            $message = is_array($data) && is_string($data['message'] ?? null) ? $data['message'] : 'HTTP '.$response->status();
            throw new SyncTransportException("Cloud answered {$response->status()}: {$message}", $response->status());
        }

        if (! is_array($data) || ($data['protocol'] ?? null) !== SyncServer::PROTOCOL) {
            throw new SyncProtocolException('The cloud did not answer in sync protocol '.SyncServer::PROTOCOL.'.');
        }

        return $data;
    }
}
