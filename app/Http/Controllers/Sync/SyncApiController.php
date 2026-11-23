<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Sync\Engine\InvalidChange;
use App\Sync\Engine\SyncProtocolException;
use App\Sync\Engine\SyncServer;
use App\Sync\Models\SyncNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * /api/sync/v1 on the cloud. Authentication and body checks are done by
 * App\Http\Middleware\AuthenticateSyncNode; the work by SyncServer.
 */
class SyncApiController extends Controller
{
    public function __construct(private SyncServer $server)
    {
    }

    public function handshake(Request $request)
    {
        return $this->run($request, fn (SyncNode $node) => $this->server->handshake($node, $request->attributes->get('sync_body')));
    }

    public function push(Request $request)
    {
        return $this->run($request, fn (SyncNode $node) => $this->server->push(
            $node,
            $request->attributes->get('sync_body'),
            (int) $request->attributes->get('sync_bytes'),
            (string) $request->attributes->get('sync_sha256'),
        ));
    }

    public function pull(Request $request)
    {
        $after = filter_var($request->query('after', 0), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $limit = filter_var($request->query('limit', 500), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($after === false || $limit === false) {
            return response()->json(['message' => 'after and limit must be whole numbers.'], 422);
        }

        return $this->run($request, fn (SyncNode $node) => $this->server->pull($node, $after, $limit));
    }

    public function ack(Request $request)
    {
        $body = $request->attributes->get('sync_body');
        $cursor = $body['cursor'] ?? null;
        if (($body['stream'] ?? null) !== SyncServer::STREAM_PULL || ! is_int($cursor) || $cursor < 0) {
            return response()->json(['message' => 'Acknowledge with stream "pull" and a cursor.'], 422);
        }

        return $this->run($request, fn (SyncNode $node) => $this->server->acknowledge($node, $cursor));
    }

    private function run(Request $request, callable $action)
    {
        $node = $request->attributes->get('sync_node');

        try {
            return $this->json($request, $action($node));
        } catch (InvalidChange $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (SyncProtocolException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            Log::channel('sync')->error('Sync request failed', ['node' => $node?->node_id, 'path' => $request->path(), 'error' => $e->getMessage()]);
            report($e);

            return response()->json(['message' => 'The cloud could not process this request. It will be retried.'], 500);
        }
    }

    /** JSON, gzip-compressed when the vessel accepts it and it is worth it. */
    private function json(Request $request, array $data): Response|JsonResponse
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (strlen($json) > 1024 && str_contains(strtolower((string) $request->header('Accept-Encoding')), 'gzip')) {
            return response(gzencode($json, 6), 200, [
                'Content-Type' => 'application/json',
                'Content-Encoding' => 'gzip',
                'Vary' => 'Accept-Encoding',
            ]);
        }

        return response($json, 200, ['Content-Type' => 'application/json']);
    }
}
