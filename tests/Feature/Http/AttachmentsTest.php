<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Ai\Ai;
use Crustum\Ai\Filesystem\FilesystemRegistry;
use Crustum\Ai\Filesystem\LocalFlysystem;
use Crustum\AiNexus\Model\Entity\AiNexusMessage;
use Laminas\Diactoros\UploadedFile;
use TestApp\Ai\Agents\SupportAgent;
use TestApp\Ai\Agents\WeatherAgent;

beforeEach(function (): void {
    Ai::manager()->resetFakeState();
    useTestAppAgents();

    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai-nexus-http-attachments-' . uniqid();
    FilesystemRegistry::register('attachments-http-test', LocalFlysystem::create($root));
    Configure::write('AiNexus.storage.attachments_disk', 'attachments-http-test');
});

afterEach(function (): void {
    FilesystemRegistry::forget('attachments-http-test');
    Configure::delete('AiNexus.storage.attachments_disk');
});

function httpUpload(string $name, string $mime, string $contents = 'file-bytes'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'ai-nexus-http-upload-');
    file_put_contents($path, $contents);

    return new UploadedFile($path, strlen($contents), UPLOAD_ERR_OK, $name, $mime);
}

function sendWithFile(string $slug, string $message, UploadedFile $file): array
{
    test()->configRequest(['files' => ['attachments' => [$file]]]);

    $parts = sendMessage($slug, $message);

    test()->configRequest(['files' => []]);

    return $parts;
}

function userMessageRow(): AiNexusMessage
{
    [, $messages] = chatTables();

    return $messages->find()->where(['role' => 'user'])->firstOrFail();
}

function attachmentPayload(string $conversationId): array
{
    test()->get("/ai_nexus/api/conversations/{$conversationId}");
    test()->assertResponseOk();

    return test()->viewVariable('data')['messages'][0]['attachments'][0];
}

it('stores an upload on the configured disk under an ai_nexus prefix', function (): void {
    fakeChatAgent(WeatherAgent::class, ['A cloudy sky.']);

    sendWithFile('test-app.ai.agents.weather-agent', 'What is in this picture?', httpUpload('sky.png', 'image/png'));

    $operator = FilesystemRegistry::get('attachments-http-test');
    $stored = [];

    foreach ($operator->listContents('ai_nexus', true) as $item) {
        $stored[] = $item->path();
    }

    expect($stored)->toHaveCount(1);
});

it('records the SDK own serialization on the user row', function (): void {
    fakeChatAgent(WeatherAgent::class, ['A cloudy sky.']);

    sendWithFile('test-app.ai.agents.weather-agent', 'What is in this picture?', httpUpload('sky.png', 'image/png'));

    $attachment = userMessageRow()->get('attachments')[0];

    expect($attachment['type'])->toBe('stored-image')
        ->and($attachment['name'])->toBe('sky.png')
        ->and($attachment['path'])->toStartWith('ai_nexus/')
        ->and($attachment['filesystem'])->toBe('attachments-http-test');
});

it('picks the file kind the SDK models from the upload type', function (): void {
    fakeChatAgent(WeatherAgent::class, fn(): string => 'Noted.');

    $kinds = [];

    foreach (
        [
        [httpUpload('a.png', 'image/png'), 'stored-image'],
        [httpUpload('a.mp3', 'audio/mpeg'), 'stored-audio'],
        [httpUpload('a.pdf', 'application/pdf'), 'stored-document'],
        ] as [$file, $expected]
    ) {
        [, $messages] = chatTables();
        $messages->deleteAll([]);

        sendWithFile('test-app.ai.agents.weather-agent', 'Look', $file);

        $kinds[] = userMessageRow()->get('attachments')[0]['type'];
    }

    expect($kinds)->toBe(['stored-image', 'stored-audio', 'stored-document']);
});

it('gives a later turn the file the earlier turn carried', function (): void {
    fakeChatAgent(SupportAgent::class, fn(): string => 'Noted.');

    $conversationId = chatConversationId(sendWithFile(
        'test-app.ai.agents.support-agent',
        'What is in this picture?',
        httpUpload('sky.png', 'image/png'),
    ));

    $payload = attachmentPayload($conversationId);

    expect($payload['name'])->toBe('sky.png')
        ->and($payload['type'])->toBe('stored-image');
});

it('deletes the stored files when the conversation goes', function (): void {
    fakeChatAgent(WeatherAgent::class, ['A cloudy sky.']);

    $conversationId = chatConversationId(sendWithFile(
        'test-app.ai.agents.weather-agent',
        'What is in this picture?',
        httpUpload('sky.png', 'image/png'),
    ));

    $path = userMessageRow()->get('attachments')[0]['path'];
    $operator = FilesystemRegistry::get('attachments-http-test');

    expect($operator->fileExists($path))->toBeTrue();

    test()->delete("/ai_nexus/api/conversations/{$conversationId}");
    test()->assertResponseCode(204);

    expect($operator->fileExists($path))->toBeFalse();
});

it('serves an attachment back without exposing where it lives', function (): void {
    fakeChatAgent(WeatherAgent::class, ['A cloudy sky.']);

    $conversationId = chatConversationId(sendWithFile(
        'test-app.ai.agents.weather-agent',
        'What is in this picture?',
        httpUpload('sky.png', 'image/png'),
    ));

    $attachment = attachmentPayload($conversationId);

    expect($attachment['name'])->toBe('sky.png')
        ->and($attachment['type'])->toBe('stored-image')
        ->and($attachment)->not->toHaveKey('path')
        ->and($attachment)->not->toHaveKey('disk')
        ->and($attachment)->not->toHaveKey('filesystem');

    $path = (string)parse_url($attachment['url'], PHP_URL_PATH);

    test()->get($path);
    test()->assertResponseOk();
    test()->assertHeaderContains('Content-Disposition', 'inline');
});

it('404s for an attachment that is not there', function (): void {
    fakeChatAgent(SupportAgent::class, ['Fine.']);

    sendMessage('test-app.ai.agents.support-agent', 'No files here');

    $message = userMessageRow();

    test()->get("/ai_nexus/api/attachments/{$message->get('id')}/0");
    test()->assertResponseCode(404);

    test()->get('/ai_nexus/api/attachments/nope/0');
    test()->assertResponseCode(404);
});

it('keeps attachments behind host middleware', function (): void {
    denyDashboard();

    test()->get('/ai_nexus/api/attachments/anything/0');
    test()->assertResponseCode(403);
});
