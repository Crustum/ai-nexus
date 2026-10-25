<?php
declare(strict_types=1);

use Crustum\AiNexus\Model\Table\AiNexusConversationsTable;
use Crustum\AiNexus\Model\Table\AiNexusMessagesTable;
use Crustum\AiNexus\Model\Table\AiNexusToolInvocationsTable;
use Crustum\AiNexus\Repository\ConversationRepository;

function repositoryTables(): array
{
    $locator = test()->getTableLocator();

    return [
        $locator->get('AiNexusConversations', ['className' => AiNexusConversationsTable::class]),
        $locator->get('AiNexusMessages', ['className' => AiNexusMessagesTable::class]),
        $locator->get('AiNexusToolInvocations', ['className' => AiNexusToolInvocationsTable::class]),
    ];
}

function testRepository(?Closure $deleteFile = null): ConversationRepository
{
    [$conversations, $messages, $invocations] = repositoryTables();

    return new ConversationRepository($conversations, $messages, $invocations, $deleteFile);
}

function saveConversationRow(string $id, string $title): void
{
    [$conversations] = repositoryTables();

    $conversation = $conversations->newEntity(['agent_class' => 'TestAgent', 'title' => $title]);
    $conversation->set('id', $id);

    $conversations->saveOrFail($conversation);
}

function saveMessageRow(string $id, string $conversationId, string $role = 'user'): void
{
    [, $messages] = repositoryTables();

    $message = $messages->newEntity([
        'conversation_id' => $conversationId,
        'role' => $role,
        'content' => 'Message content',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
        'metadata' => [],
    ]);
    $message->set('id', $id);

    $messages->saveOrFail($message);
}

function saveInvocationRow(string $id, string $conversationId): void
{
    [, , $invocations] = repositoryTables();

    $invocation = $invocations->newEntity([
        'conversation_id' => $conversationId,
        'invocation_id' => 'inv-1',
        'tool_invocation_id' => 'tool-1',
        'type' => 'tool',
        'name' => 'SearchProductsTool',
        'arguments' => [],
        'status' => 'success',
    ]);
    $invocation->set('id', $id);

    $invocations->saveOrFail($invocation);
}

it('prunes conversations older than the retention window', function (): void {
    saveConversationRow('c0000000-0000-0000-0000-000000000001', 'old');
    saveConversationRow('c0000000-0000-0000-0000-000000000002', 'new');

    [$conversations] = repositoryTables();
    $conversations->updateAll(
        ['updated_at' => new DateTimeImmutable('-30 days')],
        ['id' => 'c0000000-0000-0000-0000-000000000001'],
    );

    expect(testRepository()->prune(new DateTimeImmutable('-7 days')))->toBe(1)
        ->and($conversations->exists(['id' => 'c0000000-0000-0000-0000-000000000001']))->toBeFalse()
        ->and($conversations->exists(['id' => 'c0000000-0000-0000-0000-000000000002']))->toBeTrue();
});

it('clears all conversations', function (): void {
    saveConversationRow('c0000000-0000-0000-0000-000000000001', 'x');
    saveConversationRow('c0000000-0000-0000-0000-000000000002', 'y');

    [$conversations] = repositoryTables();

    expect(testRepository()->clear())->toBe(2)
        ->and($conversations->find()->count())->toBe(0);
});

it('cascades deletion to messages and tool invocations', function (): void {
    saveConversationRow('c0000000-0000-0000-0000-000000000001', 'x');
    saveMessageRow('m0000000-0000-0000-0000-000000000001', 'c0000000-0000-0000-0000-000000000001');
    saveInvocationRow('t0000000-0000-0000-0000-000000000001', 'c0000000-0000-0000-0000-000000000001');

    expect(testRepository()->clear())->toBe(1);

    [, $messages, $invocations] = repositoryTables();

    expect($messages->find()->count())->toBe(0)
        ->and($invocations->find()->count())->toBe(0);
});

it('deletes nothing for an empty id list', function (): void {
    saveConversationRow('c0000000-0000-0000-0000-000000000001', 'x');

    [$conversations] = repositoryTables();

    expect(testRepository()->deleteConversations([]))->toBe(0)
        ->and($conversations->find()->count())->toBe(1);
});

it('deletes attachment files of removed conversations', function (): void {
    saveConversationRow('c0000000-0000-0000-0000-000000000001', 'x');

    [, $messages] = repositoryTables();

    $message = $messages->newEntity([
        'conversation_id' => 'c0000000-0000-0000-0000-000000000001',
        'role' => 'user',
        'content' => 'With files',
        'attachments' => [
            ['disk' => 'uploads', 'path' => 'ai_nexus/first.bin'],
            ['path' => 'ai_nexus/second.bin'],
            ['name' => 'no-path-entry'],
            'plain-string-entry',
        ],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
        'metadata' => [],
    ]);
    $message->set('id', 'm0000000-0000-0000-0000-000000000001');

    $messages->saveOrFail($message);

    $deleted = [];
    $repository = testRepository(function (string $disk, string $path) use (&$deleted): void {
        $deleted[] = [$disk, $path];
    });

    expect($repository->deleteConversations(['c0000000-0000-0000-0000-000000000001']))->toBe(1)
        ->and($deleted)->toBe([
            ['uploads', 'ai_nexus/first.bin'],
            ['local', 'ai_nexus/second.bin'],
        ]);
});
