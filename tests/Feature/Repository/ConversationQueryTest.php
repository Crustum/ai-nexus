<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\AiNexus\Discovery\AgentDiscovery;
use Crustum\AiNexus\Discovery\AgentMetadata;
use Crustum\AiNexus\Discovery\InstantiationDiagnostic;
use Crustum\AiNexus\Discovery\ToolClassifier;
use Crustum\AiNexus\Model\Table\AiNexusConversationsTable;
use Crustum\AiNexus\Model\Table\AiNexusMessagesTable;
use Crustum\AiNexus\Model\Table\AiNexusToolInvocationsTable;
use Crustum\AiNexus\Repository\ConversationQuery;
use League\Container\Container;
use League\Container\ReflectionContainer;
use TestApp\Ai\Agents\SupportAgent;
use TestApp\Ai\Agents\WeatherAgent;

beforeEach(function (): void {
    Configure::write('AiNexus.discovery.paths', [dirname(__DIR__, 2) . '/TestApp/Ai/Agents']);
    Configure::write('AiNexus.discovery.ignore', []);
});

afterEach(function (): void {
    Configure::write('AiNexus.discovery.paths', []);
    Configure::write('AiNexus.discovery.ignore', []);
});

function queryTables(): array
{
    $locator = test()->getTableLocator();

    return [
        $locator->get('AiNexusConversations', ['className' => AiNexusConversationsTable::class]),
        $locator->get('AiNexusMessages', ['className' => AiNexusMessagesTable::class]),
        $locator->get('AiNexusToolInvocations', ['className' => AiNexusToolInvocationsTable::class]),
    ];
}

function testConversationQuery(): ConversationQuery
{
    [$conversations, $messages, $invocations] = queryTables();

    $container = new Container();
    $container->delegate(new ReflectionContainer());

    $discovery = new AgentDiscovery(
        new AgentMetadata(new ToolClassifier(), new InstantiationDiagnostic($container), $container),
        $container,
    );

    return new ConversationQuery($conversations, $messages, $invocations, $discovery);
}

function seedConversation(string $id, string $title, string $agent = SupportAgent::class): void
{
    [$conversations] = queryTables();

    $conversation = $conversations->newEntity(['agent_class' => $agent, 'title' => $title]);
    $conversation->set('id', $id);

    $conversations->saveOrFail($conversation);
}

function seedMessage(string $id, string $conversationId, string $role, array $data = []): void
{
    [, $messages] = queryTables();

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

function seedInvocation(string $id, string $conversationId, array $data = []): void
{
    [, , $invocations] = queryTables();

    $invocation = $invocations->newEntity(array_merge([
        'conversation_id' => $conversationId,
        'invocation_id' => 'inv-1',
        'tool_invocation_id' => 'tool-1',
        'type' => 'tool',
        'name' => 'SearchProductsTool',
        'arguments' => [],
        'status' => 'success',
    ], $data));
    $invocation->set('id', $id);

    $invocations->saveOrFail($invocation);
}

function backdateConversation(string $id, string $modifier): void
{
    [$conversations] = queryTables();
    $conversations->updateAll(['updated_at' => new DateTimeImmutable($modifier)], ['id' => $id]);
}

function listedTitles(array $filters = []): array
{
    return array_column(testConversationQuery()->paginate($filters)['data'], 'title');
}

it('lists conversations newest first', function (): void {
    seedConversation('c0000000-0000-0000-0000-000000000001', 'First question');
    seedConversation('c0000000-0000-0000-0000-000000000002', 'Second question');
    backdateConversation('c0000000-0000-0000-0000-000000000001', '-2 minutes');
    backdateConversation('c0000000-0000-0000-0000-000000000002', '-1 minute');

    expect(listedTitles())->toBe(['Second question', 'First question']);
});

it('sorts oldest first when asked', function (): void {
    seedConversation('c0000000-0000-0000-0000-000000000001', 'First question');
    seedConversation('c0000000-0000-0000-0000-000000000002', 'Second question');
    backdateConversation('c0000000-0000-0000-0000-000000000001', '-2 minutes');
    backdateConversation('c0000000-0000-0000-0000-000000000002', '-1 minute');

    expect(listedTitles(['sort' => 'oldest']))->toBe(['First question', 'Second question']);
});

it('reports the agent, tokens and tool count per row', function (): void {
    seedConversation('c0000000-0000-0000-0000-000000000001', 'Find me a hoodie');
    seedMessage(
        'm0000000-0000-0000-0000-000000000001',
        'c0000000-0000-0000-0000-000000000001',
        'assistant',
        ['prompt_tokens' => 200, 'completion_tokens' => 50],
    );
    seedInvocation('t0000000-0000-0000-0000-000000000001', 'c0000000-0000-0000-0000-000000000001');

    $row = testConversationQuery()->paginate()['data'][0];

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
    seedConversation('c0000000-0000-0000-0000-000000000001', 'This one fails');
    seedMessage('m0000000-0000-0000-0000-000000000001', 'c0000000-0000-0000-0000-000000000001', 'user');
    seedMessage('m0000000-0000-0000-0000-000000000002', 'c0000000-0000-0000-0000-000000000001', 'error');

    expect(testConversationQuery()->paginate()['data'][0]['status'])->toBe('error');
});

it('still calls a recovered tool failure a success', function (): void {
    seedConversation('c0000000-0000-0000-0000-000000000001', 'Recovered after a bad tool');
    seedMessage('m0000000-0000-0000-0000-000000000001', 'c0000000-0000-0000-0000-000000000001', 'user');
    seedMessage('m0000000-0000-0000-0000-000000000002', 'c0000000-0000-0000-0000-000000000001', 'assistant');
    seedInvocation(
        't0000000-0000-0000-0000-000000000001',
        'c0000000-0000-0000-0000-000000000001',
        ['status' => 'error', 'error' => 'Timed out'],
    );

    $row = testConversationQuery()->paginate()['data'][0];

    expect($row['status'])->toBe('success')
        ->and($row['tool_calls'])->toBe(1);
});

it('paginates at twenty-five and reports the totals', function (): void {
    for ($i = 1; $i <= 27; $i++) {
        seedConversation(sprintf('c0000000-0000-0000-0000-%012d', $i), "Question {$i}");
    }

    $first = testConversationQuery()->paginate();

    expect($first['data'])->toHaveCount(25)
        ->and($first['meta'])->toMatchArray([
            'current_page' => 1,
            'last_page' => 2,
            'per_page' => 25,
            'total' => 27,
        ])
        ->and(testConversationQuery()->paginate(['page' => 2])['data'])->toHaveCount(2);
});

it('is empty rather than broken with no conversations', function (): void {
    $result = testConversationQuery()->paginate();

    expect($result['data'])->toBe([])
        ->and($result['meta']['total'])->toBe(0);
});

it('searches conversation titles', function (): void {
    seedConversation('c0000000-0000-0000-0000-000000000001', 'Find me a hoodie');
    seedConversation('c0000000-0000-0000-0000-000000000002', 'Where is my order');

    expect(listedTitles(['search' => 'hoodie']))->toBe(['Find me a hoodie']);
});

it('searches message content, not just titles', function (): void {
    seedConversation('c0000000-0000-0000-0000-000000000001', 'Question one');
    seedConversation('c0000000-0000-0000-0000-000000000002', 'Question two');
    seedMessage(
        'm0000000-0000-0000-0000-000000000001',
        'c0000000-0000-0000-0000-000000000001',
        'assistant',
        ['content' => 'The scarf is on aisle four.'],
    );
    seedMessage(
        'm0000000-0000-0000-0000-000000000002',
        'c0000000-0000-0000-0000-000000000002',
        'assistant',
        ['content' => 'The scarf is on aisle four.'],
    );

    expect(listedTitles(['search' => 'scarf']))->toHaveCount(2)
        ->and(listedTitles(['search' => 'aisle nine']))->toBe([]);
});

it('filters by agent', function (): void {
    seedConversation('c0000000-0000-0000-0000-000000000001', 'Support question');
    seedConversation('c0000000-0000-0000-0000-000000000002', 'Weather question', WeatherAgent::class);

    expect(listedTitles(['agents' => ['test-app.ai.agents.weather-agent']]))->toBe(['Weather question']);
});

it('returns nothing for an agent slug it does not know', function (): void {
    seedConversation('c0000000-0000-0000-0000-000000000001', 'Support question');

    expect(listedTitles(['agents' => ['App\\Models\\User']]))->toBe([])
        ->and(listedTitles(['agents' => ['nope.not.here']]))->toBe([]);
});

it('filters by status', function (): void {
    seedConversation('c0000000-0000-0000-0000-000000000001', 'This one fails');
    seedConversation('c0000000-0000-0000-0000-000000000002', 'This one works');
    seedMessage('m0000000-0000-0000-0000-000000000001', 'c0000000-0000-0000-0000-000000000001', 'user');
    seedMessage('m0000000-0000-0000-0000-000000000002', 'c0000000-0000-0000-0000-000000000001', 'error');
    seedMessage('m0000000-0000-0000-0000-000000000003', 'c0000000-0000-0000-0000-000000000002', 'user');

    expect(listedTitles(['status' => 'error']))->toBe(['This one fails'])
        ->and(listedTitles(['status' => 'success']))->toBe(['This one works'])
        ->and(listedTitles(['status' => 'maybe']))->toHaveCount(2);
});

it('filters by tool used', function (): void {
    seedConversation('c0000000-0000-0000-0000-000000000001', 'With a tool');
    seedConversation('c0000000-0000-0000-0000-000000000002', 'Without a tool');
    seedInvocation('t0000000-0000-0000-0000-000000000001', 'c0000000-0000-0000-0000-000000000001');

    expect(listedTitles(['tools' => ['SearchProductsTool']]))->toBe(['With a tool']);
});

it('filters by date range', function (): void {
    seedConversation('c0000000-0000-0000-0000-000000000001', 'Old question');
    seedConversation('c0000000-0000-0000-0000-000000000002', 'Recent question');
    backdateConversation('c0000000-0000-0000-0000-000000000001', '-10 days');

    $yesterday = (new DateTimeImmutable('-1 day'))->format('Y-m-d');
    $fiveDaysAgo = (new DateTimeImmutable('-5 days'))->format('Y-m-d');

    expect(listedTitles(['from' => $yesterday]))->toBe(['Recent question'])
        ->and(listedTitles(['to' => $fiveDaysAgo]))->toBe(['Old question']);
});

it('composes filters rather than replacing them', function (): void {
    seedConversation('c0000000-0000-0000-0000-000000000001', 'Support that fails');
    seedConversation('c0000000-0000-0000-0000-000000000002', 'Support that works');
    seedConversation('c0000000-0000-0000-0000-000000000003', 'Weather question', WeatherAgent::class);
    seedMessage('m0000000-0000-0000-0000-000000000001', 'c0000000-0000-0000-0000-000000000001', 'error');

    expect(listedTitles(['agents' => ['test-app.ai.agents.support-agent'], 'status' => 'error']))
        ->toBe(['Support that fails']);
});

it('ships the filter options with discovery agents and used tools', function (): void {
    seedConversation('c0000000-0000-0000-0000-000000000001', 'Find me a hoodie');
    seedInvocation('t0000000-0000-0000-0000-000000000001', 'c0000000-0000-0000-0000-000000000001');

    $filters = testConversationQuery()->filterOptions();

    expect(array_column($filters['agents'], 'slug'))->toContain('test-app.ai.agents.support-agent')
        ->and($filters['tools'])->toContain('SearchProductsTool');
});

it('reads the display name from the stored class for orphaned rows', function (): void {
    seedConversation('c0000000-0000-0000-0000-000000000001', 'Archived thread', 'No\\Such\\Agent');

    $row = testConversationQuery()->paginate()['data'][0];

    expect($row['agent_name'])->toBe('Agent')
        ->and($row['agent_available'])->toBeFalse();
});
