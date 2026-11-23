<?php

namespace Tests\Feature\Security;

use App\Sync\Files\FileColumns;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Sync\Files\FileSyncTestCase;

/**
 * Uploads are stored under a name and extension the server chooses from
 * the content (Checkpoint 7), and shown with headers that keep a browser
 * from running them.
 */
class UploadSecurityTest extends FileSyncTestCase
{
    private function libraryUpload(string $name, string $content)
    {
        return $this->as('gm.srd@office.test')->post('/attachments/upload', ['title' => 'Test', 'file' => $this->upload($name, $content)]);
    }

    public function test_the_stored_name_and_extension_come_from_the_server(): void
    {
        $this->onCloud(function () {
            $pdf = $this->pdf();
            $this->libraryUpload('invoice.html', $pdf)->assertOk();

            $row = DB::table('attachments')->sole();
            $this->assertMatchesRegularExpression('#^attachments/\d+/[0-9a-f-]{36}\.pdf$#', $row->path);
            $this->assertSame(['invoice.html', 'application/pdf', hash('sha256', $pdf)], [$row->original_filename, $row->mime_type, $row->sha256]);
            $this->assertSame($pdf, file_get_contents($this->fileOn('cloud', $row->path)));
        });
    }

    public function test_content_that_is_not_an_accepted_type_is_refused_whatever_its_name(): void
    {
        $this->onCloud(function () {
            foreach ([
                'report.pdf' => '<html><script>alert(document.cookie)</script></html>',
                'shell.php' => $this->pdf(),
                'photo.jpg' => "<?php system(\$_GET['c']); ?>",
                'image.svg' => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>',
            ] as $name => $content) {
                $this->libraryUpload($name, $content)->assertSessionHasErrors('file');
            }
            $this->assertSame(0, DB::table('attachments')->count());
            $this->assertSame([], glob($this->fileRoots['cloud']['local'].'/attachments/*/*') ?: []);
        });
    }

    public function test_a_photo_or_signature_must_really_be_an_image(): void
    {
        $this->onCloud(function () {
            $this->as('tech.supt@office.test')->post('/change/file', ['photo' => $this->upload('me.jpg', "<?php echo 'x'; ?>")])->assertSessionHasErrors('photo');
            $this->assertNull(DB::table('users')->where('email', 'tech.supt@office.test')->value('photo'));
            $this->assertSame([], glob($this->fileRoots['cloud']['public'].'/images/userphoto/*') ?: []);

            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
            $this->as('tech.supt@office.test')->post('/change/file', ['photo' => $this->upload('me.phtml.gif', $png)])->assertRedirect();
            $this->assertMatchesRegularExpression('#^images/userphoto/[a-z0-9]{32}\.png$#', DB::table('users')->where('email', 'tech.supt@office.test')->value('photo'));
        });
    }

    public function test_a_stored_file_is_shown_so_the_browser_cannot_run_it(): void
    {
        $this->onCloud(function () {
            $this->libraryUpload('notes.txt', "Engine room notes, 21 Nov.\nNothing to report.\n")->assertOk();
            $this->libraryUpload('doc.pdf', $this->pdf())->assertOk();
            [$text, $pdf] = DB::table('attachments')->orderBy('id')->pluck('id')->all();

            $response = $this->as('gm.srd@office.test')->get("/attachments/{$text}/view")->assertOk();
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringStartsWith('sandbox', (string) $response->headers->get('Content-Security-Policy'));
            $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));

            $response = $this->as('gm.srd@office.test')->get("/attachments/{$pdf}/view")->assertOk();
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertNull($response->headers->get('Content-Security-Policy'), 'the browser\'s PDF viewer must work');
        });
    }

    public function test_sync_only_reads_or_writes_files_at_safe_paths(): void
    {
        $spec = FileColumns::spec('attachments', 'path');
        foreach ([
            'attachments/12/0b6f8a52-3c1e-4e8f-9a51-7d0e2f6b9c11.pdf' => true,
            'attachments/12/legacy-name.JPEG' => true,
            'attachments/../.env' => false,
            'attachments/12/../../../etc/passwd.pdf' => false,
            'attachments/12/x.php' => false,
            'attachments/12/x.pdf.php' => false,
            'attachments/12/x' => false,
            '/attachments/12/x.pdf' => false,
            'attachments\\12\\x.pdf' => false,
            'attachments/12/x y.pdf' => false,
            "attachments/12/x.pdf\0.php" => false,
            'images/userphoto/x.jpg' => false,
            'attachments/12/.htaccess' => false,
        ] as $path => $safe) {
            $this->assertSame($safe, FileColumns::isSafePath($spec, $path), $path);
        }
    }
}
