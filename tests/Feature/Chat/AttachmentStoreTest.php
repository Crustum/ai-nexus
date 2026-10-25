<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Ai\Filesystem\FilesystemRegistry;
use Crustum\Ai\Filesystem\LocalFlysystem;
use Crustum\AiNexus\Chat\AttachmentStore;
use Laminas\Diactoros\UploadedFile;

beforeEach(function (): void {
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai-nexus-attachments-' . uniqid();
    FilesystemRegistry::register('attachments-test', LocalFlysystem::create($root));
    Configure::write('AiNexus.storage.attachments_disk', 'attachments-test');
});

afterEach(function (): void {
    FilesystemRegistry::forget('attachments-test');
    Configure::delete('AiNexus.storage.attachments_disk');
});

function testUpload(string $name, string $mime, string $contents = 'file-bytes'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'ai-nexus-upload-');
    file_put_contents($path, $contents);

    return new UploadedFile($path, strlen($contents), UPLOAD_ERR_OK, $name, $mime);
}

it('stores an upload under an ai_nexus prefix', function (): void {
    $files = (new AttachmentStore())->store([testUpload('sky.png', 'image/png')]);

    expect($files)->toHaveCount(1)
        ->and($files[0]->toArray()['path'])->toStartWith('ai_nexus/');
});

it('records the SDK own serialization for a stored file', function (): void {
    $files = (new AttachmentStore())->store([testUpload('sky.png', 'image/png')]);

    expect($files[0]->toArray())->toMatchArray([
        'type' => 'stored-image',
        'name' => 'sky.png',
        'filesystem' => 'attachments-test',
    ]);
});

it('picks the file kind the SDK models from the upload type', function (): void {
    $store = new AttachmentStore();

    $kinds = [
        $store->store([testUpload('a.png', 'image/png')])[0]->toArray()['type'],
        $store->store([testUpload('a.mp3', 'audio/mpeg')])[0]->toArray()['type'],
        $store->store([testUpload('a.pdf', 'application/pdf')])[0]->toArray()['type'],
    ];

    expect($kinds)->toBe(['stored-image', 'stored-audio', 'stored-document']);
});

it('round-trips stored files through their serialization', function (): void {
    $store = new AttachmentStore();
    $files = $store->store([testUpload('sky.png', 'image/png')]);

    $rehydrated = $store->rehydrate($store->toArray($files));

    expect($rehydrated)->toHaveCount(1)
        ->and($rehydrated[0]->name())->toBe('sky.png')
        ->and($rehydrated[0]->toArray())->toBe($files[0]->toArray());
});

it('skips entries the SDK cannot rebuild', function (): void {
    expect((new AttachmentStore())->rehydrate([['type' => 'nope'], 'plain-string-entry']))->toBe([]);
});

it('reads the legacy disk key the way the SDK does', function (): void {
    $rehydrated = (new AttachmentStore())->rehydrate([
        ['type' => 'stored-image', 'name' => 'sky.png', 'path' => 'ai_nexus/sky', 'disk' => 'local'],
    ]);

    expect($rehydrated)->toHaveCount(1)
        ->and($rehydrated[0]->name())->toBe('sky.png');
});
