<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Sync\Files\FileServer;
use App\Sync\Models\SyncNode;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * /api/sync/v1/files on the cloud (Checkpoint 7). The vessel is already
 * authenticated by App\Http\Middleware\AuthenticateSyncNode; FileServer
 * decides what it may send and fetch.
 */
class SyncFileController extends Controller
{
    public function __construct(private FileServer $files)
    {
    }

    public function wanted(Request $request)
    {
        return response()->json($this->files->wanted($this->node($request), (int) $request->query('limit', 200)));
    }

    public function upload(Request $request, string $table, string $uuid, string $column)
    {
        $int = fn (string $header) => preg_match('/^\d{1,15}$/', (string) $request->header($header)) ? (int) $request->header($header) : null;

        $answer = $this->files->receive($this->node($request), $table, strtolower($uuid), $column, [
            'missing' => $request->header('X-File-Missing') === '1',
            'sha256' => $request->header('X-File-SHA256'),
            'size' => $int('X-File-Size'),
            'offset' => $int('X-File-Offset'),
            'chunk_sha256' => $request->header('X-Sync-Body-SHA256'),
        ], $request->getContent());

        return response()->json($answer['body'], $answer['status']);
    }

    public function download(Request $request, string $table, string $uuid, string $column)
    {
        $answer = $this->files->send($this->node($request), $table, strtolower($uuid), $column,
            max(0, (int) $request->query('offset', 0)), max(1, (int) $request->query('length', 1)));

        return is_array($answer['body'])
            ? response()->json($answer['body'], $answer['status'])
            : new Response($answer['body'], $answer['status'], $answer['headers']);
    }

    private function node(Request $request): SyncNode
    {
        return $request->attributes->get('sync_node');
    }
}
