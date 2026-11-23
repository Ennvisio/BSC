<?php

namespace App\Http\Middleware;

use App\Support\AppMode;
use App\Sync\Engine\SyncServer;
use App\Sync\Models\SyncNode;
use App\Sync\SyncNodes;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards /api/sync/v1 on the cloud.
 *
 * - The API exists only in cloud mode; a vessel server answers 404.
 * - Each vessel server authenticates as its node: the X-Sync-Node header
 *   and the secret token as a bearer token. The cloud stores only a hash of
 *   the token (Checkpoint 2), compared in constant time.
 * - Wrong credentials are limited to 10 a minute per address.
 * - The request body may be gzip-compressed and must match the SHA-256 in
 *   X-Sync-Body-SHA256, so a truncated or damaged upload is refused whole.
 */
class AuthenticateSyncNode
{
    public function __construct(private AppMode $mode, private SyncNodes $nodes)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->mode->isCloud()) {
            abort(404);
        }
        if ($this->nodes->thisNodeId() === null) {
            return $this->refuse(503, 'Sync is not set up on this server (SYNC_NODE_ID).');
        }
        if ((string) $request->header('X-Sync-Protocol') !== (string) SyncServer::PROTOCOL) {
            return $this->refuse(400, 'Unsupported sync protocol. This server speaks version '.SyncServer::PROTOCOL.'.');
        }

        $limiterKey = 'sync-auth:'.$request->ip();
        if (RateLimiter::tooManyAttempts($limiterKey, 10)) {
            return $this->refuse(429, 'Too many failed sign-ins. Try again in a minute.');
        }

        $node = $this->nodes->authenticate((string) $request->header('X-Sync-Node'), (string) $request->bearerToken());
        if ($node === null) {
            RateLimiter::hit($limiterKey, 60);

            return $this->refuse(401, 'Unknown node or wrong token.');
        }
        if ($node->kind !== SyncNode::KIND_VESSEL || ! $node->vessel_id) {
            return $this->refuse(403, 'Only vessel servers sync with the cloud.');
        }

        // POST bodies are JSON. PUT bodies are pieces of a file (Checkpoint 7):
        // binary, checked against their own SHA-256 by App\Sync\Files\FileServer.
        if ($request->isMethod('POST')) {
            $body = $this->body($request);
            if ($body instanceof Response) {
                return $body;
            }
            $request->attributes->set('sync_body', $body['data']);
            $request->attributes->set('sync_bytes', $body['bytes']);
            $request->attributes->set('sync_sha256', $body['sha256']);
        }

        $request->attributes->set('sync_node', $node);

        return $next($request);
    }

    /** @return array{data: array, bytes: int, sha256: string}|Response */
    private function body(Request $request): array|Response
    {
        $max = (int) config('sync.http.max_request_bytes', 32 * 1024 * 1024);
        $raw = $request->getContent();

        if (strtolower((string) $request->header('Content-Encoding')) === 'gzip') {
            $raw = $this->gunzip($raw, $max);
            if ($raw === false) {
                return $this->refuse(400, 'The request body is not valid gzip.');
            }
        }
        if (strlen($raw) > $max) {
            return $this->refuse(413, 'The request body is too large.');
        }

        $sha = hash('sha256', $raw);
        $expected = strtolower((string) $request->header('X-Sync-Body-SHA256'));
        if ($expected === '' || ! hash_equals($expected, $sha)) {
            return $this->refuse(400, 'The request body does not match its checksum.');
        }

        $data = json_decode($raw, true, 64);
        if (! is_array($data)) {
            return $this->refuse(400, 'The request body is not a JSON object.');
        }

        return ['data' => $data, 'bytes' => strlen($raw), 'sha256' => $sha];
    }

    /**
     * Inflates in steps and stops just past $max, so a small upload that
     * expands enormously (a "zip bomb") never fills memory.
     *
     * @return string|false the body, a string longer than $max if it is too large, or false if it is not gzip
     */
    private function gunzip(string $compressed, int $max): string|false
    {
        $context = @inflate_init(ZLIB_ENCODING_GZIP);
        if ($context === false) {
            return false;
        }

        $out = '';
        foreach (str_split($compressed, 64 * 1024) ?: [''] as $chunk) {
            $part = @inflate_add($context, $chunk, ZLIB_SYNC_FLUSH);
            if ($part === false) {
                return false;
            }
            $out .= $part;
            if (strlen($out) > $max) {
                return $out;
            }
        }

        // A body cut short inflates without error but never reaches the end.
        return inflate_get_status($context) === ZLIB_STREAM_END ? $out : false;
    }

    private function refuse(int $status, string $message): Response
    {
        return response()->json(['message' => $message], $status);
    }
}
