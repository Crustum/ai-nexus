<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Ai\Ai;
use Crustum\Ai\Responses\Data\Meta;
use Crustum\Ai\Responses\Data\TextUsage;
use Crustum\Ai\Responses\Data\ToolCall;
use Crustum\Ai\Responses\TextResponse;
use Crustum\Ai\Streaming\Event\ProviderToolEvent;
use Crustum\AiNexus\Chat\ProviderToolRecorder;
use TestApp\Ai\Agents\FlakyToolAgent;
use TestApp\Ai\Agents\SupportAgent;
use TestApp\Ai\Agents\WeatherAgent;

beforeEach(function (): void {
    Ai::manager()->resetFakeState();
    useTestAppAgents();
});

function conversationList(string $query = ''): array
{
    test()->get('/ai_nexus/api/conversations' . ($query === '' ? '' : "?{$query}"));
    test()->assertResponseOk();
    test()->assertResponseRegExp('/^\{"data":/');

    return test()->viewVariable('data');
}

function listedHttpTitles(string $query = ''): array
{
    return array_column(conversationList($query)['data'], 'title');
}

function showConversation(string $id): array
{
    test()->get("/ai_nexus/api/conversations/{$id}");
    test()->assertResponseOk();

    return test()->viewVariable('data');
}

it('lists conversations newest first', function (): void {
    fakeChatAgent(SupportAgent::class, fn(): string => 'Answered.');

    sendMessage('test-app.ai.agents.support-agent', 'First question');
    sendMessage('test-app.ai.agents.support-agent', 'Second question');

    expect(array_column(conversationList()['data'], 'title'))->toBe(['Second question', 'First question']);
});

it('sorts oldest first when asked', function (): void {
    fakeChatAgent(SupportAgent::class, fn(): string => 'Answered.');

    sendMessage('test-app.ai.agents.support-agent', 'First question');
    sendMessage('test-app.ai.agents.support-agent', 'Second question');

    expect(listedHttpTitles('sort=oldest'))->toBe(['First question', 'Second question']);
});

it('reports the agent, tokens and tool count per row', function (): void {
    fakeChatAgent(SupportAgent::class, [
        new ToolCall(id: 'call_1', name: 'SearchProductsTool', arguments: ['query' => 'hoodie']),
        new TextResponse('Found it.', new TextUsage(200, 50), new Meta('openai', 'gpt-5.6-luna')),
    ]);

    sendMessage('test-app.ai.agents.support-agent', 'Find me a hoodie');

    $row = conversationList()['data'][0];

    expect($row['agent_name'])->toBe('SupportAgent')
        ->and($row['agent_slug'])->toBe('test-app.ai.agents.support-agent')
        ->and($row['agent_available'])->toBeTrue()
        ->and($row['tool_calls'])->toBe(1)
        ->and($row['prompt_tokens'])->toBe(200)
        ->and($row['completion_tokens'])->toBe(50)
        ->and($row['total_tokens'])->toBe(250)
        ->and($row['status'])->toBe('success');
});

it('marks a conversation as error when a message failed', function (): void {
    fakeChatAgent(SupportAgent::class, [
        fn(): never => throw new RuntimeException('Provider exploded'),
    ]);

    sendMessage('test-app.ai.agents.support-agent', 'This one fails');

    expect(conversationList()['data'][0]['status'])->toBe('error');
});

it('still calls a recovered tool failure a success', function (): void {
    fakeChatAgent(FlakyToolAgent::class, [
        new ToolCall(id: 'call_1', name: 'BrokenLedgerTool', arguments: ['entry' => '42']),
    ]);

    sendMessage('test-app.ai.agents.flaky-tool-agent', 'Look up entry 42');

    [$conversations, $messages, $invocations] = chatTables();

    $conversation = $conversations->newEntity([
        'agent_class' => SupportAgent::class,
        'title' => 'Recovered after a bad tool',
    ]);
    $conversation->set('id', 'c0000000-0000-0000-0000-000000000001');

    $conversations->saveOrFail($conversation);

    foreach (
        [
        ['id' => 'm0000000-0000-0000-0000-000000000001', 'role' => 'user', 'content' => 'Find me a hoodie'],
        ['id' => 'm0000000-0000-0000-0000-000000000002', 'role' => 'assistant', 'content' => 'Found it anyway.'],
        ] as $seed
    ) {
        $message = $messages->newEntity(array_merge([
            'conversation_id' => 'c0000000-0000-0000-0000-000000000001',
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [],
            'meta' => [],
            'metadata' => [],
        ], $seed));
        $message->set('id', $seed['id']);
        $messages->saveOrFail($message);
    }

    $invocation = $invocations->newEntity([
        'conversation_id' => 'c0000000-0000-0000-0000-000000000001',
        'invocation_id' => 'inv_1',
        'tool_invocation_id' => 'tool_1',
        'type' => 'tool',
        'name' => 'SearchProductsTool',
        'arguments' => [],
        'status' => 'error',
        'error' => 'Upstream timed out',
    ]);
    $invocation->set('id', 't0000000-0000-0000-0000-000000000001');

    $invocations->saveOrFail($invocation);

    $rows = conversationList()['data'];
    $row = null;

    foreach ($rows as $candidate) {
        if ($candidate['id'] === 'c0000000-0000-0000-0000-000000000001') {
            $row = $candidate;
        }
    }

    expect($row['status'])->toBe('success')
        ->and($row['tool_calls'])->toBe(1);
});

it('paginates at twenty-five and reports the totals', function (): void {
    fakeChatAgent(SupportAgent::class, fn(): string => 'Answered.');

    for ($i = 1; $i <= 27; $i++) {
        sendMessage('test-app.ai.agents.support-agent', "Question {$i}");
    }

    $first = conversationList();

    expect($first['data'])->toHaveCount(25)
        ->and($first['meta'])->toMatchArray([
            'current_page' => 1,
            'last_page' => 2,
            'per_page' => 25,
            'total' => 27,
        ])
        ->and(conversationList('page=2')['data'])->toHaveCount(2);
});

it('ships the filter options with the list', function (): void {
    fakeChatAgent(SupportAgent::class, [
        new ToolCall(id: 'call_1', name: 'SearchProductsTool', arguments: ['query' => 'hoodie']),
        'Found it.',
    ]);

    sendMessage('test-app.ai.agents.support-agent', 'Find me a hoodie');

    $filters = conversationList()['filters'];

    expect(array_column($filters['agents'], 'slug'))->toContain('test-app.ai.agents.support-agent')
        ->and($filters['tools'])->toContain('SearchProductsTool');
});

it('is empty rather than broken with no conversations', function (): void {
    $result = conversationList();

    expect($result['data'])->toBe([])
        ->and($result['meta']['total'])->toBe(0);
});

it('searches conversation titles', function (): void {
    fakeChatAgent(SupportAgent::class, fn(): string => 'Answered.');

    sendMessage('test-app.ai.agents.support-agent', 'Find me a hoodie');
    sendMessage('test-app.ai.agents.support-agent', 'Where is my order');

    expect(listedHttpTitles('search=hoodie'))->toBe(['Find me a hoodie']);
});

it('searches message content, not just titles', function (): void {
    fakeChatAgent(SupportAgent::class, fn(): string => 'The scarf is on aisle four.');

    sendMessage('test-app.ai.agents.support-agent', 'Question one');
    sendMessage('test-app.ai.agents.support-agent', 'Question two');

    expect(listedHttpTitles('search=scarf'))->toHaveCount(2)
        ->and(listedHttpTitles('search=aisle+nine'))->toBe([]);
});

it('filters by agent', function (): void {
    fakeChatAgent(SupportAgent::class, fn(): string => 'Answered.');
    fakeChatAgent(WeatherAgent::class, fn(): string => 'Sunny.');

    sendMessage('test-app.ai.agents.support-agent', 'Support question');
    sendMessage('test-app.ai.agents.weather-agent', 'Weather question');

    expect(listedHttpTitles('agents[]=test-app.ai.agents.weather-agent'))->toBe(['Weather question']);
});

it('filters by several agents at once', function (): void {
    fakeChatAgent(SupportAgent::class, fn(): string => 'Answered.');
    fakeChatAgent(WeatherAgent::class, fn(): string => 'Sunny.');

    sendMessage('test-app.ai.agents.support-agent', 'Support question');
    sendMessage('test-app.ai.agents.weather-agent', 'Weather question');

    expect(listedHttpTitles('agents[]=test-app.ai.agents.weather-agent&agents[]=test-app.ai.agents.support-agent'))
        ->toHaveCount(2);
});

it('returns nothing for an agent slug it does not know', function (): void {
    fakeChatAgent(SupportAgent::class, fn(): string => 'Answered.');

    sendMessage('test-app.ai.agents.support-agent', 'Support question');

    expect(listedHttpTitles('agents[]=App\\Models\\User'))->toBe([])
        ->and(listedHttpTitles('agents[]=nope.not.here'))->toBe([]);
});

it('filters by status', function (): void {
    fakeChatAgent(SupportAgent::class, fn(string $prompt): string => str_contains($prompt, 'fails')
        ? throw new RuntimeException('Provider exploded')
        : 'Answered.');

    sendMessage('test-app.ai.agents.support-agent', 'This one fails');
    sendMessage('test-app.ai.agents.support-agent', 'This one works');

    expect(listedHttpTitles('status=error'))->toBe(['This one fails'])
        ->and(listedHttpTitles('status=success'))->toBe(['This one works']);
});

it('filters by tool used', function (): void {
    fakeChatAgent(SupportAgent::class, [
        new ToolCall(id: 'call_1', name: 'SearchProductsTool', arguments: ['query' => 'hoodie']),
        'Found it.',
    ]);

    sendMessage('test-app.ai.agents.support-agent', 'With a tool');

    fakeChatAgent(SupportAgent::class, fn(): string => 'No tool needed.');

    sendMessage('test-app.ai.agents.support-agent', 'Without a tool');

    expect(listedHttpTitles('tools[]=SearchProductsTool'))->toBe(['With a tool']);
});

it('filters by date range', function (): void {
    fakeChatAgent(SupportAgent::class, fn(): string => 'Answered.');

    sendMessage('test-app.ai.agents.support-agent', 'Old question');

    [$conversations] = chatTables();
    $conversations->updateAll(['updated_at' => new DateTimeImmutable('-10 days')], []);

    sendMessage('test-app.ai.agents.support-agent', 'Recent question');

    $yesterday = (new DateTimeImmutable('-1 day'))->format('Y-m-d');
    $fiveDaysAgo = (new DateTimeImmutable('-5 days'))->format('Y-m-d');

    expect(listedHttpTitles("from={$yesterday}"))->toBe(['Recent question'])
        ->and(listedHttpTitles("to={$fiveDaysAgo}"))->toBe(['Old question']);
});

it('composes filters rather than replacing them', function (): void {
    $responses = fn(string $prompt): string => str_contains($prompt, 'fails')
        ? throw new RuntimeException('Provider exploded')
        : 'Answered.';

    fakeChatAgent(SupportAgent::class, $responses);
    fakeChatAgent(WeatherAgent::class, $responses);

    sendMessage('test-app.ai.agents.support-agent', 'Support that fails');
    sendMessage('test-app.ai.agents.support-agent', 'Support that works');
    sendMessage('test-app.ai.agents.weather-agent', 'Weather question');

    expect(listedHttpTitles('agents[]=test-app.ai.agents.support-agent&status=error'))
        ->toBe(['Support that fails']);
});

it('rejects a status it does not understand', function (): void {
    test()->get('/ai_nexus/api/conversations?status=maybe');
    test()->assertResponseCode(422);
});

it('keeps the list behind host middleware', function (): void {
    denyDashboard();

    test()->get('/ai_nexus/api/conversations');
    test()->assertResponseCode(403);
});

it('has no clear-all route', function (): void {
    test()->post('/ai_nexus/api/conversations/clear', []);
    test()->assertResponseCode(404);
});

it('replays a conversation in order', function (): void {
    fakeChatAgent(SupportAgent::class, ['First answer.', 'Second answer.']);

    $id = chatConversationId(sendMessage('test-app.ai.agents.support-agent', 'First question'));
    sendMessage('test-app.ai.agents.support-agent', 'Second question', $id);

    $conversation = showConversation($id);

    expect(array_column($conversation['messages'], 'role'))
        ->toBe(['user', 'assistant', 'user', 'assistant'])
        ->and(array_column($conversation['messages'], 'content'))
        ->toBe(['First question', 'First answer.', 'Second question', 'Second answer.']);
});

it('reports the agent it belongs to', function (): void {
    fakeChatAgent(SupportAgent::class, ['Answer.']);

    $id = chatConversationId(sendMessage('test-app.ai.agents.support-agent', 'Question'));

    $conversation = showConversation($id);

    expect($conversation['agent_class'])->toBe(SupportAgent::class)
        ->and($conversation['agent_slug'])->toBe('test-app.ai.agents.support-agent');
});

it('totals the tokens across the thread', function (): void {
    fakeChatAgent(SupportAgent::class, [
        new TextResponse('One.', new TextUsage(100, 20), new Meta('openai', 'gpt-5.6-luna')),
        new TextResponse('Two.', new TextUsage(42, 9), new Meta('openai', 'gpt-5.6-luna')),
    ]);

    $id = chatConversationId(sendMessage('test-app.ai.agents.support-agent', 'First'));
    sendMessage('test-app.ai.agents.support-agent', 'Second', $id);

    expect(showConversation($id)['totals'])->toBe([
        'prompt_tokens' => 142,
        'completion_tokens' => 29,
        'total_tokens' => 171,
    ]);
});

it('includes the error rows so a refresh still shows what went wrong', function (): void {
    fakeChatAgent(SupportAgent::class, [
        function (): never {
            usleep(2000);

            throw new RuntimeException('Provider exploded');
        },
    ]);

    $id = chatConversationId(sendMessage('test-app.ai.agents.support-agent', 'Anything'));

    $messages = showConversation($id)['messages'];

    expect(array_column($messages, 'role'))->toBe(['user', 'error'])
        ->and($messages[1]['metadata']['exception_class'])->toBe(RuntimeException::class);
});

it('carries per-message usage and duration', function (): void {
    fakeChatAgent(SupportAgent::class, [
        new TextResponse('Answer.', new TextUsage(11, 3), new Meta('openai', 'gpt-5.6-luna')),
    ]);

    $id = chatConversationId(sendMessage('test-app.ai.agents.support-agent', 'Question'));

    $assistant = showConversation($id)['messages'][1];

    expect($assistant['usage']['input_tokens'])->toBe(11)
        ->and($assistant['usage']['output_tokens'])->toBe(3)
        ->and($assistant['duration_ms'])->toBeInt()
        ->and($assistant['meta']['model'])->toBe('gpt-5.6-luna');
});

it('replays tool calls alongside the messages', function (): void {
    fakeChatAgent(SupportAgent::class, [
        new ToolCall(id: 'call_1', name: 'SearchProductsTool', arguments: ['query' => 'hoodie']),
        'Found three matches.',
    ]);

    $id = chatConversationId(sendMessage('test-app.ai.agents.support-agent', 'Find me a hoodie'));

    $invocation = showConversation($id)['tool_invocations'][0];

    expect($invocation['type'])->toBe('tool')
        ->and($invocation['name'])->toBe('SearchProductsTool')
        ->and($invocation['arguments'])->toBe(['query' => 'hoodie'])
        ->and($invocation['status'])->toBe('success')
        ->and($invocation['duration_ms'])->toBeInt()
        ->and($invocation['started_at'])->not->toBeNull()
        ->and($invocation['message_id'])->not->toBeNull();
});

it('reports a provider tool with the provider own status word', function (): void {
    [$conversations, ,] = chatTables();

    $conversation = $conversations->newEntity([
        'agent_class' => SupportAgent::class,
        'title' => 'Search run',
    ]);
    $conversation->set('id', 'c0000000-0000-0000-0000-000000000001');

    $conversations->saveOrFail($conversation);

    [, , $invocations] = chatTables();
    $recorder = new ProviderToolRecorder(
        $invocations,
        'c0000000-0000-0000-0000-000000000001',
    );
    $recorder->record(
        new ProviderToolEvent(
            'evt_1',
            'srvtoolu_1',
            'server_tool_use',
            ['name' => 'web_search'],
            'searching',
            time(),
            'anthropic',
        ),
        'inv_1',
    );

    $invocation = showConversation('c0000000-0000-0000-0000-000000000001')['tool_invocations'][0];

    expect($invocation['type'])->toBe('provider_tool')
        ->and($invocation['name'])->toBe('web_search')
        ->and($invocation['status'])->toBe('pending')
        ->and($invocation['provider_status'])->toBe('searching');
});

it('404s for an unknown conversation', function (): void {
    test()->get('/ai_nexus/api/conversations/does-not-exist');
    test()->assertResponseCode(404);
});

it('deletes a conversation and everything hanging off it', function (): void {
    fakeChatAgent(SupportAgent::class, ['Answer.']);

    $id = chatConversationId(sendMessage('test-app.ai.agents.support-agent', 'Question'));

    [, , $invocations] = chatTables();

    $invocation = $invocations->newEntity([
        'conversation_id' => $id,
        'invocation_id' => 'inv_1',
        'tool_invocation_id' => 'tool_1',
        'type' => 'tool',
        'name' => 'SearchProductsTool',
        'arguments' => [],
        'status' => 'success',
    ]);
    $invocation->set('id', 't0000000-0000-0000-0000-000000000001');

    $invocations->saveOrFail($invocation);

    test()->delete("/ai_nexus/api/conversations/{$id}");
    test()->assertResponseCode(204);

    [$conversations, $messages] = chatTables();

    expect($conversations->find()->count())->toBe(0)
        ->and($messages->find()->count())->toBe(0)
        ->and($invocations->find()->count())->toBe(0);
});

it('keeps the conversation endpoints behind host middleware', function (): void {
    denyDashboard();

    test()->get('/ai_nexus/api/conversations/anything');
    test()->assertResponseCode(403);

    test()->delete('/ai_nexus/api/conversations/anything');
    test()->assertResponseCode(403);
});

it('renames a conversation and returns the updated row', function (): void {
    fakeChatAgent(SupportAgent::class, ['Answered.']);

    $id = chatConversationId(sendMessage('test-app.ai.agents.support-agent', 'Find me a hoodie'));

    test()->patch('/ai_nexus/api/conversations/' . $id, ['title' => 'Hoodie search, take three']);
    test()->assertResponseOk();

    expect(test()->viewVariable('data')['title'])->toBe('Hoodie search, take three');

    [$conversations] = chatTables();

    expect($conversations->get($id)->get('title'))->toBe('Hoodie search, take three');
});

it('leaves the messages untouched on rename', function (): void {
    fakeChatAgent(SupportAgent::class, ['Answered.']);

    $id = chatConversationId(sendMessage('test-app.ai.agents.support-agent', 'Find me a hoodie'));

    test()->patch('/ai_nexus/api/conversations/' . $id, ['title' => 'Renamed']);

    [, $messages] = chatTables();

    expect($messages->find()->where(['role' => 'user'])->firstOrFail()->get('content'))
        ->toBe('Find me a hoodie');
});

it('requires a title on rename', function (): void {
    fakeChatAgent(SupportAgent::class, ['Answered.']);

    $id = chatConversationId(sendMessage('test-app.ai.agents.support-agent', 'Find me a hoodie'));

    test()->patch('/ai_nexus/api/conversations/' . $id, ['title' => '']);
    test()->assertResponseCode(422);

    test()->patch('/ai_nexus/api/conversations/' . $id, []);
    test()->assertResponseCode(422);
});

it('404s a rename for a conversation that is not there', function (): void {
    test()->patch('/ai_nexus/api/conversations/nope', ['title' => 'Renamed']);
    test()->assertResponseCode(404);
});

it('keeps rename behind host middleware', function (): void {
    denyDashboard();

    test()->patch('/ai_nexus/api/conversations/anything', ['title' => 'Renamed']);
    test()->assertResponseCode(403);
});

it('still lists a conversation whose agent is gone', function (): void {
    fakeChatAgent(SupportAgent::class, [
        new ToolCall(id: 'call_1', name: 'SearchProductsTool', arguments: ['query' => 'hoodie']),
        'Found it.',
    ]);

    $id = chatConversationId(sendMessage('test-app.ai.agents.support-agent', 'Find me a hoodie'));

    Configure::write('AiNexus.discovery.paths', [dirname(__DIR__, 2) . '/TestApp/Ai/Tools']);

    $rows = conversationList()['data'];
    $row = null;

    foreach ($rows as $candidate) {
        if ($candidate['id'] === $id) {
            $row = $candidate;
        }
    }

    expect($row)->not->toBeNull()
        ->and($row['agent_name'])->toBe('SupportAgent')
        ->and($row['agent_available'])->toBeFalse();
});

it('replays the full thread for an agent that is gone', function (): void {
    fakeChatAgent(SupportAgent::class, function (): string {
        // Keep the assistant row in a later millisecond than the user row: ids
        // are time-sortable uuid7 and datetimes persist with second precision,
        // so same-ms rows would order randomly on a fast database.
        usleep(5000);

        return 'Answer.';
    });

    $id = chatConversationId(sendMessage('test-app.ai.agents.support-agent', 'Question'));

    Configure::write('AiNexus.discovery.paths', [dirname(__DIR__, 2) . '/TestApp/Ai/Tools']);

    $conversation = showConversation($id);

    expect($conversation['agent_available'])->toBeFalse()
        ->and(array_column($conversation['messages'], 'role'))->toBe(['user', 'assistant']);
});

it('cannot be filtered by an agent that is no longer discovered', function (): void {
    fakeChatAgent(SupportAgent::class, ['Answer.']);

    sendMessage('test-app.ai.agents.support-agent', 'Question');

    Configure::write('AiNexus.discovery.paths', [dirname(__DIR__, 2) . '/TestApp/Ai/Tools']);

    expect(listedHttpTitles('agents[]=test-app.ai.agents.support-agent'))->toBe([])
        ->and(conversationList()['data'])->toHaveCount(1);
});

it('can still rename and delete an orphaned conversation', function (): void {
    fakeChatAgent(SupportAgent::class, ['Answer.']);

    $id = chatConversationId(sendMessage('test-app.ai.agents.support-agent', 'Question'));

    Configure::write('AiNexus.discovery.paths', [dirname(__DIR__, 2) . '/TestApp/Ai/Tools']);

    test()->patch('/ai_nexus/api/conversations/' . $id, ['title' => 'Archived']);
    test()->assertResponseOk();

    test()->delete("/ai_nexus/api/conversations/{$id}");
    test()->assertResponseCode(204);

    expect(conversationList()['data'])->toBe([]);
});
