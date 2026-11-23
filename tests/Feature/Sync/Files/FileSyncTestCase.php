<?php

namespace Tests\Feature\Sync\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Feature\Sync\Modules\BusinessSyncTestCase;

/**
 * Two servers with attachments enabled, each with its own storage and
 * images folders, so a file is only "on the cloud" once it was sent there.
 */
abstract class FileSyncTestCase extends BusinessSyncTestCase
{
    protected string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 4).'/storage/framework/testing/file-sync-'.bin2hex(random_bytes(4));
        foreach (['cloud', 'ship'] as $server) {
            $this->fileRoots[$server] = ['local' => "{$this->root}/{$server}/storage", 'public' => "{$this->root}/{$server}/app"];
            @mkdir($this->fileRoots[$server]['local'], 0777, true);
            @mkdir($this->fileRoots[$server]['public'], 0777, true);
        }
        parent::setUp();
        config([
            'sync.enabled_tables' => 'currencies,ranks,categories,@master,@inventory,@pms,@requisitions,@attachments',
            'sync.files.chunk_bytes' => 1024,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    /** Bytes fileinfo recognises as a PDF, $kb kilobytes long, different every call. */
    protected function pdf(int $kb = 5): string
    {
        $body = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n";
        while (strlen($body) < $kb * 1024) {
            $body .= '% '.bin2hex(random_bytes(30))."\n";
        }

        return $body."%%EOF\n";
    }

    /**
     * A real uploaded file: its type is worked out from the content, as for
     * a browser upload (Laravel's fake files report a type from the name).
     */
    protected function upload(string $name, string $content): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($tmp, $content);

        return new UploadedFile($tmp, $name, null, null, true);
    }

    /** The absolute path of a stored file on one server. */
    protected function fileOn(string $server, string $relative, string $disk = 'local'): string
    {
        return $this->fileRoots[$server][$disk === 'local' ? 'local' : 'public'].'/'.$relative;
    }

    /** A crew member attaches a PDF to a draft line on board and submits; returns [order id, attachment id, bytes]. */
    protected function crewAttachesAndSubmits(int $kb = 5): array
    {
        $orderId = $this->draftOnBoard(['ME-100' => 2]);
        $bytes = $this->pdf($kb);
        $attachmentId = $this->onShip(function () use ($orderId, $bytes) {
            $this->as('co@mvtest.test')->post("/requisition/{$orderId}/items/{$this->itemId('ME-100')}/attachments/upload", [
                'title' => 'Maker quotation', 'file' => $this->upload('quotation.html', $bytes),
            ])->assertOk();

            return (int) DB::table('attachments')->value('id');
        });
        $this->submitOnBoard($orderId);

        return [$orderId, $attachmentId, $bytes];
    }

    protected function attachmentOn(string $server, string $uuid): ?object
    {
        return $this->on($server, fn () => DB::table('attachments')->where('sync_uuid', $uuid)->first());
    }
}
