<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Chat;

use Cake\Core\Configure;
use Crustum\Ai\Files\File;
use Crustum\Ai\Files\StoredAudio;
use Crustum\Ai\Files\StoredDocument;
use Crustum\Ai\Files\StoredImage;
use Crustum\Ai\Filesystem\FilesystemRegistry;
use Crustum\Ai\Utility\Uuid;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Puts uploads on a Flysystem disk and hands the SDK its own file objects.
 *
 * Files are never base64'd into the database: a 5MB PDF is ~6.7MB of text and
 * a `TEXT` column caps at 64KB. What the message row stores is the SDK's own
 * serialization — `{type, name, path, filesystem}` — which is exactly the shape
 * `File::fromArray()` reads back, so rehydration follows the SDK rather than a
 * format of our own that would drift from it.
 */
class AttachmentStore
{
    /**
     * Where uploads live on the configured disk.
     */
    protected const string PREFIX = 'ai_nexus';

    /**
     * Store the uploads and return the SDK file objects for this turn.
     *
     * @param array<int, \Psr\Http\Message\UploadedFileInterface> $uploads Uploaded files.
     * @return array<int, \Crustum\Ai\Files\File>
     */
    public function store(array $uploads): array
    {
        return array_map($this->storeOne(...), $uploads);
    }

    /**
     * Rebuild the SDK file objects from a stored message's serialization.
     *
     * @param array<int, mixed> $attachments Stored attachment serializations.
     * @return array<int, \Crustum\Ai\Files\File>
     */
    public function rehydrate(array $attachments): array
    {
        return array_values(array_filter(array_map(
            fn(mixed $attachment): ?File => is_array($attachment) ? File::fromArray($attachment) : null,
            $attachments,
        )));
    }

    /**
     * The stored serialization of the given files.
     *
     * @param array<int, \Crustum\Ai\Files\File> $files SDK file objects.
     * @return array<int, array<string, mixed>>
     */
    public function toArray(array $files): array
    {
        return array_map(
            fn(File $file): array => method_exists($file, 'toArray') ? $file->toArray() : [],
            $files,
        );
    }

    /**
     * Store one upload on the configured disk and hand back its SDK file object.
     *
     * @param \Psr\Http\Message\UploadedFileInterface $upload Uploaded file.
     * @return \Crustum\Ai\Files\File
     */
    protected function storeOne(UploadedFileInterface $upload): File
    {
        $disk = $this->disk();
        $name = (string)$upload->getClientFilename();
        $path = self::PREFIX . '/' . Uuid::v7();

        FilesystemRegistry::get($disk)->write($path, $upload->getStream()->getContents());

        $mime = (string)$upload->getClientMediaType();

        $file = match (true) {
            str_starts_with($mime, 'image/') => new StoredImage($path, $disk),
            str_starts_with($mime, 'audio/') => new StoredAudio($path, $disk),
            default => new StoredDocument($path, $disk),
        };

        return $file->as($name);
    }

    /**
     * The Flysystem disk uploads are written to.
     *
     * @return string
     */
    protected function disk(): string
    {
        $disk = Configure::read('AiNexus.storage.attachments_disk', 'local');

        return is_string($disk) && $disk !== '' ? $disk : 'local';
    }
}
