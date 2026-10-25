<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Controller;

use Cake\Core\Configure;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Crustum\Ai\Filesystem\FilesystemRegistry;
use League\Flysystem\FilesystemException;

/**
 * Stored attachments, served back to the thread.
 */
class AttachmentsController extends AiNexusController
{
    /**
     * Serve one of a message's stored attachments back to the thread.
     *
     * The path is read from the message row, never from the request, so there
     * is no traversal to defend against — a caller can only ask for an
     * attachment that a stored message already claims.
     *
     * @param string $message Message id.
     * @param string $index Attachment position on the message.
     * @return \Cake\Http\Response
     */
    public function show(string $message, string $index): Response
    {
        [, $messages] = $this->tables();

        $row = $messages->find()->where(['id' => $message])->first();

        if ($row === null) {
            throw new NotFoundException('Message not found.');
        }

        $stored = $row->get('attachments');
        $attachment = is_array($stored) ? array_values($stored)[(int)$index] ?? null : null;

        if (!is_array($attachment) || !isset($attachment['path']) || !is_string($attachment['path'])) {
            throw new NotFoundException('Attachment not found.');
        }

        $disk = $attachment['filesystem'] ?? $attachment['disk'] ?? null;

        if (!is_string($disk) || $disk === '') {
            $configured = Configure::read('AiNexus.storage.attachments_disk', 'local');
            $disk = is_string($configured) && $configured !== '' ? $configured : 'local';
        }

        $operator = FilesystemRegistry::get($disk);

        try {
            $exists = $operator->fileExists($attachment['path']);

            if (!$exists) {
                throw new NotFoundException('Attachment file is gone.');
            }

            $contents = $operator->read($attachment['path']);

            try {
                $mime = $operator->mimeType($attachment['path']);
            } catch (FilesystemException) {
                $mime = 'application/octet-stream';
            }
        } catch (FilesystemException $filesystemException) {
            throw new NotFoundException('Attachment file is gone.', $filesystemException->getCode(), previous: $filesystemException);
        }

        $name = $attachment['name'] ?? basename($attachment['path']);

        return $this->response
            ->withType($mime !== '' ? $mime : 'application/octet-stream')
            ->withHeader('Content-Disposition', 'inline; filename="' . str_replace('"', '', (string)$name) . '"')
            ->withStringBody($contents);
    }
}
