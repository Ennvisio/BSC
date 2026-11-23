<?php

namespace App\Support\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores an uploaded file under a name chosen by the server (Checkpoint 7).
 *
 * The file's type is the one PHP's fileinfo finds in its content, and the
 * extension comes from that type through the allow-list below, never from
 * the name the browser sent: "invoice.php" holding a PDF is stored as
 * <random>.pdf, and a script calling itself "photo.jpg" is refused because
 * its content is not an image. The name is random, so it cannot be guessed
 * or collide with a file another server created. The SHA-256 is worked out
 * as the file is stored, for integrity checks and sync.
 */
class SafeUpload
{
    /** Content type => the only extension a file of that type is stored with. */
    public const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt',
    ];

    /** Extensions a stored file may have: what sync accepts in a path. */
    public static function storedExtensions(): array
    {
        // Older uploads kept the browser's extension; these are the ones
        // the upload rules ever let through.
        return array_values(array_unique([...array_values(self::EXTENSIONS), 'jpeg', 'jpe']));
    }

    /**
     * The extension for this upload, from its content.
     *
     * @param  list<string>  $allowedMimes
     *
     * @throws ValidationException the content is not one of the allowed types
     */
    public static function extension(UploadedFile $file, array $allowedMimes, string $field = 'file'): string
    {
        $mime = (string) $file->getMimeType();
        $extension = self::EXTENSIONS[$mime] ?? null;
        if ($extension === null || ! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([$field => 'This type of file is not accepted.']);
        }

        return $extension;
    }

    /**
     * Into storage/app/{directory}, which the web server never serves.
     *
     * @param  list<string>  $allowedMimes
     * @return array{path: string, sha256: string, size: int, mime: string}
     */
    public static function storePrivate(UploadedFile $file, string $directory, array $allowedMimes, string $disk = 'local', string $field = 'file'): array
    {
        $extension = self::extension($file, $allowedMimes, $field);
        $sha256 = hash_file('sha256', $file->getRealPath());
        $size = (int) $file->getSize();
        $mime = (string) $file->getMimeType();
        $path = $file->storeAs(trim($directory, '/'), Str::uuid().'.'.$extension, $disk);

        return ['path' => $path, 'sha256' => $sha256, 'size' => $size, 'mime' => $mime];
    }

    /**
     * Into images/{folder} under the application folder, served as a static
     * file (photos, signatures, certificate copies). PHP never runs there
     * (httpd.conf), and the extension can only be an image's or a PDF's.
     *
     * @param  list<string>  $allowedMimes
     * @return string the path stored in the database, e.g. images/userphoto/<random>.jpg
     */
    public static function storePublic(UploadedFile $file, string $folder, array $allowedMimes, string $field = 'file'): string
    {
        $extension = self::extension($file, $allowedMimes, $field);
        $name = Str::lower(Str::random(32)).'.'.$extension;
        $relative = 'images/'.trim($folder, '/').'/'.$name;
        $file->move(self::publicRoot().'/images/'.trim($folder, '/'), $name);

        return $relative;
    }

    /** Where images/... paths live: the application folder (bootstrap/app.php serves it as the web root). */
    public static function publicRoot(): string
    {
        return rtrim((string) (config('filesystems.disks.public_images.root') ?: base_path()), '/\\');
    }
}
