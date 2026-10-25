<?php
declare(strict_types=1);

use Crustum\Ai\Contracts\Tool;
use Crustum\Ai\Event\InvokingTool;
use Crustum\Ai\Event\ToolInvoked;
use Crustum\Ai\Streaming\Event\ProviderToolEvent;
use Crustum\AiNexus\Chat\AiNexusRecorder;
use Crustum\AiNexus\Chat\ProviderToolRecorder;
use Crustum\AiNexus\Model\Table\AiNexusConversationsTable;
use Crustum\AiNexus\Model\Table\AiNexusToolInvocationsTable;
use TestApp\Ai\Agents\SupportAgent;
use TestApp\Ai\Tools\SearchProductsTool;

function recorderTables(): AiNexusToolInvocationsTable
{
    return test()->getTableLocator()->get(
        'AiNexusToolInvocations',
        ['className' => AiNexusToolInvocationsTable::class],
    );
}

function recorderConversation(): string
{
    $conversations = test()->getTableLocator()->get(
        'AiNexusConversations',
        ['className' => AiNexusConversationsTable::class],
    );

    $conversation = $conversations->newEntity(['agent_class' => 'TestAgent', 'title' => 'Recorder run']);
    $conversation->set('id', 'c0000000-0000-0000-0000-000000000001');

    return (string)$conversations->saveOrFail($conversation)->get('id');
}

function invokingToolEvent(
    string $invocationId = 'inv_1',
    string $toolInvocationId = 'tool_1',
    ?Tool $tool = null,
    array $arguments = [],
): InvokingTool {
    $tool ??= new SearchProductsTool();

    return new InvokingTool(
        $invocationId,
        $toolInvocationId,
        new SupportAgent(),
        $tool,
        $arguments,
    );
}

function toolInvokedEvent(
    string $invocationId = 'inv_1',
    string $toolInvocationId = 'tool_1',
    mixed $result = 'result',
): ToolInvoked {
    return new ToolInvoked(
        $invocationId,
        $toolInvocationId,
        new SupportAgent(),
        new SearchProductsTool(),
        [],
        $result,
        1.5,
    );
}

it('records a tool call as pending', function (): void {
    (new AiNexusRecorder(recorderTables(), recorderConversation()))
        ->toolInvoking(invokingToolEvent(arguments: ['query' => 'hoodie']));

    $row = recorderTables()->find()->firstOrFail();

    expect($row->get('type'))->toBe('tool')
        ->and($row->get('name'))->toBe('SearchProductsTool')
        ->and($row->get('arguments'))->toBe(['query' => 'hoodie'])
        ->and($row->get('status'))->toBe('pending')
        ->and($row->get('started_at'))->not->toBeNull()
        ->and($row->get('finished_at'))->toBeNull();
});

it('moves the pending row to success on the result', function (): void {
    $recorder = new AiNexusRecorder(recorderTables(), recorderConversation());
    $recorder->toolInvoking(invokingToolEvent());
    $recorder->toolInvoked(toolInvokedEvent(result: 'three matches'));

    $row = recorderTables()->find()->firstOrFail();

    expect($row->get('status'))->toBe('success')
        ->and($row->get('result'))->toBe('three matches')
        ->and($row->get('finished_at'))->not->toBeNull()
        ->and($row->get('duration_ms'))->toBeGreaterThanOrEqual(0);
});

it('ignores results without a pending row', function (): void {
    (new AiNexusRecorder(recorderTables(), recorderConversation()))
        ->toolInvoked(toolInvokedEvent());

    expect(recorderTables()->find()->count())->toBe(0);
});

it('attaches a run rows to the assistant turn', function (): void {
    $recorder = new AiNexusRecorder(recorderTables(), recorderConversation());
    $recorder->toolInvoking(invokingToolEvent());
    $recorder->linkToMessage('inv_1', 'message-1');

    expect(recorderTables()->find()->firstOrFail()->get('message_id'))->toBe('message-1');
});

it('leaves other runs rows unattached', function (): void {
    $recorder = new AiNexusRecorder(recorderTables(), recorderConversation());
    $recorder->toolInvoking(invokingToolEvent('inv_1'));
    $recorder->toolInvoking(invokingToolEvent('inv_2'));
    $recorder->linkToMessage('inv_1', 'message-1');

    $rows = recorderTables()->find()->orderBy(['invocation_id' => 'ASC'])->all()->toList();

    expect($rows[0]->get('message_id'))->toBe('message-1')
        ->and($rows[1]->get('message_id'))->toBeNull();
});

function providerToolEvent(string $itemId, string $type, string $status, array $data = []): ProviderToolEvent
{
    return new ProviderToolEvent('evt_' . uniqid(), $itemId, $type, $data, $status, time(), 'anthropic');
}

function recordProviderTools(ProviderToolEvent ...$events): void
{
    $recorder = new ProviderToolRecorder(recorderTables(), recorderConversation());

    foreach ($events as $event) {
        $recorder->record($event, 'inv_1');
    }
}

it('opens a pending card when a provider tool starts', function (): void {
    recordProviderTools(
        providerToolEvent('srvtoolu_1', 'server_tool_use', 'started', ['name' => 'web_search']),
    );

    $row = recorderTables()->find()->firstOrFail();

    expect($row->get('type'))->toBe('provider_tool')
        ->and($row->get('status'))->toBe('pending')
        ->and($row->get('provider_status'))->toBe('started')
        ->and($row->get('finished_at'))->toBeNull();
});

it('resolves the same card when the result arrives', function (): void {
    recordProviderTools(
        providerToolEvent('srvtoolu_1', 'server_tool_use', 'started', ['name' => 'web_search']),
        providerToolEvent('srvtoolu_1', 'web_search_tool_result', 'completed', ['content' => ['a result']]),
    );

    $row = recorderTables()->find()->firstOrFail();

    expect($row->get('status'))->toBe('success')
        ->and($row->get('provider_status'))->toBe('completed')
        ->and($row->get('result'))->toBe(['content' => ['a result']])
        ->and($row->get('finished_at'))->not->toBeNull();
});

it('takes the tool name from the payload, not the block type', function (): void {
    recordProviderTools(
        providerToolEvent('srvtoolu_1', 'server_tool_use', 'started', ['name' => 'web_search']),
    );

    expect(recorderTables()->find()->firstOrFail()->get('name'))->toBe('web_search');
});

it('falls back to the block type when the payload has no name', function (): void {
    recordProviderTools(
        providerToolEvent('item_1', 'web_search_call', 'in_progress'),
    );

    expect(recorderTables()->find()->firstOrFail()->get('name'))->toBe('web_search_call');
});

it('treats an unrecognized status as still running', function (): void {
    recordProviderTools(
        providerToolEvent('item_1', 'code_interpreter_call', 'interpreting'),
    );

    $row = recorderTables()->find()->firstOrFail();

    expect($row->get('status'))->toBe('pending')
        ->and($row->get('provider_status'))->toBe('interpreting');
});

it('records a failure with the provider reason', function (): void {
    recordProviderTools(
        providerToolEvent('item_1', 'web_search_call', 'in_progress'),
        providerToolEvent('item_1', 'web_search_call', 'failed', ['error' => 'Upstream search unavailable']),
    );

    $row = recorderTables()->find()->firstOrFail();

    expect($row->get('status'))->toBe('error')
        ->and($row->get('error'))->toBe('Upstream search unavailable');
});

it('explains a failure the provider gave no reason for', function (): void {
    recordProviderTools(
        providerToolEvent('item_1', 'web_search_call', 'failed'),
    );

    expect(recorderTables()->find()->firstOrFail()->get('error'))->toContain('failed');
});

it('writes a second card when the provider ids do not line up', function (): void {
    recordProviderTools(
        providerToolEvent('srvtoolu_1', 'server_tool_use', 'started', ['name' => 'web_search']),
        providerToolEvent('other_id', 'web_search_tool_result', 'completed'),
    );

    $rows = recorderTables()->find()->orderBy(['id' => 'ASC'])->all()->toList();

    expect($rows)->toHaveCount(2)
        ->and([$rows[0]->get('status'), $rows[1]->get('status')])->toBe(['pending', 'success']);
});
