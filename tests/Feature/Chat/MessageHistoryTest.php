<?php
declare(strict_types=1);

use Crustum\Ai\Messages\AssistantMessage;
use Crustum\Ai\Messages\ToolResultMessage;
use Crustum\Ai\Messages\UserMessage;
use Crustum\AiNexus\Chat\AttachmentStore;
use Crustum\AiNexus\Chat\MessageHistory;
use Crustum\AiNexus\Model\Table\AiNexusMessagesTable;

function testMessageHistory(): MessageHistory
{
    $messages = test()->getTableLocator()->get('AiNexusMessages', ['className' => AiNexusMessagesTable::class]);

    return new MessageHistory($messages, new AttachmentStore());
}

function seedHistoryMessage(string $id, string $conversationId, string $role, array $data = []): void
{
    $messages = test()->getTableLocator()->get('AiNexusMessages', ['className' => AiNexusMessagesTable::class]);

    $message = $messages->newEntity(array_merge([
        'conversation_id' => $conversationId,
        'role' => $role,
        'content' => 'Message content',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
        'metadata' => [],
    ], $data));
    $message->set('id', $id);

    $messages->saveOrFail($message);
}

it('reads turns oldest first', function (): void {
    seedHistoryMessage('m0000000-0000-0000-0000-000000000001', 'conv-1', 'user', ['content' => 'First']);
    seedHistoryMessage('m0000000-0000-0000-0000-000000000002', 'conv-1', 'assistant', ['content' => 'Second']);

    $history = testMessageHistory()->for('conv-1');

    expect($history)->toHaveCount(2)
        ->and($history[0]->content)->toBe('First')
        ->and($history[1]->content)->toBe('Second');
});

it('scopes history to one conversation', function (): void {
    seedHistoryMessage('m0000000-0000-0000-0000-000000000001', 'conv-1', 'user', ['content' => 'First']);
    seedHistoryMessage('m0000000-0000-0000-0000-000000000002', 'conv-2', 'user', ['content' => 'Other']);

    $history = testMessageHistory()->for('conv-1');

    expect($history)->toHaveCount(1)
        ->and($history[0]->content)->toBe('First');
});

it('caps the rows it considers', function (): void {
    seedHistoryMessage('m0000000-0000-0000-0000-000000000001', 'conv-1', 'user', ['content' => 'First']);
    seedHistoryMessage('m0000000-0000-0000-0000-000000000002', 'conv-1', 'user', ['content' => 'Second']);

    expect(testMessageHistory()->for('conv-1', 1))->toHaveCount(1)
        ->and(testMessageHistory()->for('conv-1', 1)[0]->content)->toBe('Second');
});

it('leaves error rows out of the history the agent sees', function (): void {
    seedHistoryMessage('m0000000-0000-0000-0000-000000000001', 'conv-1', 'user', ['content' => 'This one fails']);
    seedHistoryMessage('m0000000-0000-0000-0000-000000000002', 'conv-1', 'error', ['content' => 'Boom']);

    $history = testMessageHistory()->for('conv-1');

    expect($history)->toHaveCount(1)
        ->and($history[0]->content)->toBe('This one fails');
});

it('leaves a plain turn without an attachment wrapper', function (): void {
    seedHistoryMessage('m0000000-0000-0000-0000-000000000001', 'conv-1', 'user', ['content' => 'No files here']);

    expect(testMessageHistory()->for('conv-1')[0])->not->toBeInstanceOf(UserMessage::class);
});

it('rehydrates stored files onto the user message', function (): void {
    seedHistoryMessage('m0000000-0000-0000-0000-000000000001', 'conv-1', 'user', [
        'content' => 'What is in this picture?',
        'attachments' => [
            ['type' => 'stored-image', 'name' => 'sky.png', 'path' => 'ai_nexus/sky', 'filesystem' => 'local'],
            'plain-string-entry',
        ],
    ]);

    $history = testMessageHistory()->for('conv-1');

    expect($history[0])->toBeInstanceOf(UserMessage::class)
        ->and($history[0]->attachments)->toHaveCount(1)
        ->and($history[0]->attachments->first()->name())->toBe('sky.png');
});

it('replays a tool turn back into the SDK message sequence', function (): void {
    seedHistoryMessage('m0000000-0000-0000-0000-000000000001', 'conv-1', 'user', ['content' => 'Find me a hoodie']);
    seedHistoryMessage('m0000000-0000-0000-0000-000000000002', 'conv-1', 'assistant', [
        'content' => 'Found three matches.',
        'tool_calls' => [['id' => 'call_1', 'name' => 'search_products', 'arguments' => ['query' => 'hoodie']]],
        'tool_results' => [[
            'id' => 'call_1',
            'name' => 'search_products',
            'arguments' => ['query' => 'hoodie'],
            'result' => '3 matches',
        ]],
    ]);

    $history = testMessageHistory()->for('conv-1');

    expect($history)->toHaveCount(4)
        ->and($history[1])->toBeInstanceOf(AssistantMessage::class)
        ->and($history[2])->toBeInstanceOf(ToolResultMessage::class)
        ->and($history[3]->content)->toBe('Found three matches.');
});

it('replays only the text of a tool call that never completed', function (): void {
    seedHistoryMessage('m0000000-0000-0000-0000-000000000001', 'conv-1', 'assistant', [
        'content' => 'Working on it.',
        'tool_calls' => [['id' => 'call_1', 'name' => 'search_products', 'arguments' => []]],
        'tool_results' => [],
    ]);
    seedHistoryMessage('m0000000-0000-0000-0000-000000000002', 'conv-1', 'assistant', [
        'content' => null,
        'tool_calls' => [['id' => 'call_2', 'name' => 'search_products', 'arguments' => []]],
        'tool_results' => [],
    ]);

    $history = testMessageHistory()->for('conv-1');

    expect($history)->toHaveCount(1)
        ->and($history[0]->content)->toBe('Working on it.');
});
