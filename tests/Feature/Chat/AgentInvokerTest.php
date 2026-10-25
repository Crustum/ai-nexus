<?php
declare(strict_types=1);

use Cake\Event\EventManager;
use Crustum\Ai\Ai;
use Crustum\Ai\Contracts\Conversational;
use Crustum\Ai\Event\StreamingAgent;
use Crustum\Ai\Responses\Data\Meta;
use Crustum\Ai\Responses\Data\TextUsage;
use Crustum\Ai\Responses\Data\ToolCall;
use Crustum\Ai\Responses\TextResponse;
use Crustum\AiNexus\Chat\AiNexusConversationalAgent;
use Crustum\AiNexus\Chat\StreamEmitter;
use Crustum\AiNexus\Discovery\DiscoveredAgent;
use TestApp\Ai\Agents\ExtractorAgent;
use TestApp\Ai\Agents\FlakyToolAgent;
use TestApp\Ai\Agents\SupportAgent;
use TestApp\Ai\Agents\TwoToolAgent;
use TestApp\Ai\Agents\WeatherAgent;

beforeEach(function (): void {
    Ai::manager()->resetFakeState();
});

function chatMessages(): array
{
    [, $messages] = chatTables();

    return $messages->find()->orderBy(['id' => 'ASC'])->all()->toList();
}

function watchStreamedHistory(array &$captured): callable
{
    $listener = function (StreamingAgent $event) use (&$captured): void {
        $agent = $event->prompt->agent;

        $captured[] = $agent instanceof Conversational ? [...$agent->messages()] : [];
    };

    EventManager::instance()->on(StreamingAgent::eventName(), $listener);

    return $listener;
}

function stopWatchingStreamedHistory(callable $listener): void
{
    EventManager::instance()->off(StreamingAgent::eventName(), $listener);
}

it('streams an answer as vercel protocol parts', function (): void {
    fakeChatAgent(SupportAgent::class, ['Certainly, here is the answer.']);

    $result = invokeChat(SupportAgent::class, 'How do I reset a password?');

    expect(chatPartTypes($result['parts']))
        ->toContain('data-ai-nexus-start')
        ->toContain('start')
        ->toContain('text-start')
        ->toContain('text-delta')
        ->toContain('finish')
        ->toContain('data-ai-nexus-end');

    $written = $result['emitter']->written();

    expect(end($written))->toBe('[DONE]');
});

it('announces the conversation before any model output', function (): void {
    fakeChatAgent(SupportAgent::class, ['Hi.']);

    $parts = invokeChat(SupportAgent::class, 'Hello')['parts'];
    $first = $parts[0];

    [$conversations] = chatTables();

    expect($first['type'])->toBe('data-ai-nexus-start')
        ->and($first['data']['conversationId'])->toBe($conversations->find()->first()->get('id'))
        ->and($first['data']['userMessageId'])->not->toBeEmpty();
});

it('emits exactly one start part even across steps', function (): void {
    fakeChatAgent(WeatherAgent::class, ['Sunny.']);

    $types = chatPartTypes(invokeChat(WeatherAgent::class, 'Weather in Tbilisi?')['parts']);

    expect(array_count_values($types)['start'])->toBe(1);
});

it('holds the finish part until the end of the stream', function (): void {
    fakeChatAgent(SupportAgent::class, ['Done.']);

    $types = chatPartTypes(invokeChat(SupportAgent::class, 'Anything else?')['parts']);

    expect(array_slice($types, -2))->toBe(['finish', 'data-ai-nexus-end']);
});

it('reuses an existing conversation when one is given', function (): void {
    fakeChatAgent(SupportAgent::class, ['One.', 'Two.']);

    $conversationId = chatConversationId(
        invokeChat(SupportAgent::class, 'First question')['parts'],
    );

    invokeChat(SupportAgent::class, 'Second question', $conversationId);

    [$conversations] = chatTables();

    expect($conversations->find()->count())->toBe(1)
        ->and(count(chatMessages()))->toBe(4);
});

it('titles the conversation from the first message', function (): void {
    fakeChatAgent(SupportAgent::class, ['Sure.']);

    invokeChat(SupportAgent::class, 'How do I reset a password?');

    [$conversations] = chatTables();

    expect($conversations->find()->first()->get('title'))->toBe('How do I reset a password?');
});

it('stores the user turn and the assistant turn', function (): void {
    fakeChatAgent(SupportAgent::class, function (): string {
        // Keep the two rows in different milliseconds: ids are time-sortable
        // uuid7 and datetimes persist with second precision, so same-ms rows
        // would order randomly on a fast database.
        usleep(5000);

        return 'The answer is 42.';
    });

    invokeChat(SupportAgent::class, 'What is the answer?');

    $messages = chatMessages();

    expect($messages)->toHaveCount(2)
        ->and($messages[0]->get('role'))->toBe('user')
        ->and($messages[0]->get('content'))->toBe('What is the answer?')
        ->and($messages[1]->get('role'))->toBe('assistant')
        ->and($messages[1]->get('content'))->toBe('The answer is 42.');
});

it('promotes token counts and keeps the full usage breakdown', function (): void {
    fakeChatAgent(SupportAgent::class, [
        new TextResponse(
            'Counted.',
            new TextUsage(inputTokens: 142, outputTokens: 89, reasoningTokens: 7),
            new Meta('openai', 'gpt-5.6-luna'),
        ),
    ]);

    invokeChat(SupportAgent::class, 'Count my tokens');

    [, $messages] = chatTables();
    $assistant = $messages->find()->where(['role' => 'assistant'])->firstOrFail();

    expect($assistant->get('prompt_tokens'))->toBe(142)
        ->and($assistant->get('completion_tokens'))->toBe(89)
        ->and($assistant->get('usage'))->toBe([
            'input_tokens' => 142,
            'output_tokens' => 89,
            'cache_read_input_tokens' => null,
            'cache_write_input_tokens' => null,
            'reasoning_tokens' => 7,
        ]);
});

it('records the provider and model that actually ran', function (): void {
    fakeChatAgent(SupportAgent::class, ['Ran.']);

    invokeChat(SupportAgent::class, 'Which model are you?');

    [, $messages] = chatTables();
    $assistant = $messages->find()->where(['role' => 'assistant'])->firstOrFail();

    expect($assistant->get('meta')['provider'])->toBe('openai')
        ->and($assistant->get('meta')['model'])->toBe('gpt-5.6-luna');
});

it('measures how long the turn took', function (): void {
    fakeChatAgent(SupportAgent::class, ['Quick.']);

    invokeChat(SupportAgent::class, 'Be quick');

    [, $messages] = chatTables();

    expect($messages->find()->where(['role' => 'assistant'])->firstOrFail()->get('duration_ms'))
        ->toBeGreaterThanOrEqual(0);
});

it('reports the usage on the closing part', function (): void {
    fakeChatAgent(SupportAgent::class, [
        new TextResponse('Done.', new TextUsage(10, 4), new Meta('openai', 'gpt-5.6-luna')),
    ]);

    $end = chatPart(invokeChat(SupportAgent::class, 'Finish up')['parts'], 'data-ai-nexus-end');

    expect($end['data']['usage']['input_tokens'])->toBe(10)
        ->and($end['data']['usage']['output_tokens'])->toBe(4)
        ->and($end['data']['assistantMessageId'])->not->toBeEmpty();
});

it('touches the conversation so retention and history order correctly', function (): void {
    fakeChatAgent(SupportAgent::class, ['One.', 'Two.']);

    $conversationId = chatConversationId(
        invokeChat(SupportAgent::class, 'First')['parts'],
    );

    [$conversations] = chatTables();
    $conversations->updateAll(
        ['updated_at' => new DateTimeImmutable('-1 day')],
        ['id' => $conversationId],
    );

    invokeChat(SupportAgent::class, 'Second', $conversationId);

    expect($conversations->get($conversationId)->get('updated_at')->isToday())->toBeTrue();
});

it('keeps the user turn even when the agent blows up', function (): void {
    fakeChatAgent(SupportAgent::class, [
        fn(): never => throw new RuntimeException('Provider exploded'),
    ]);

    invokeChat(SupportAgent::class, 'This will fail');

    [, $messages] = chatTables();

    expect($messages->find()->where(['role' => 'user'])->firstOrFail()->get('content'))
        ->toBe('This will fail')
        ->and($messages->find()->where(['role' => 'error'])->count())->toBe(1);
});

it('turns a provider failure into an inline error card', function (): void {
    fakeChatAgent(SupportAgent::class, [
        fn(): never => throw new RuntimeException('Rate limit exceeded for openai.'),
    ]);

    $error = chatPart(invokeChat(SupportAgent::class, 'Anything')['parts'], 'error');

    expect($error['errorText'])->toBe('Rate limit exceeded for openai.')
        ->and($error['data']['exceptionClass'])->toBe(RuntimeException::class)
        ->and($error['data']['stackTrace'])->not->toBeEmpty()
        ->and($error['data']['messageId'])->not->toBeEmpty();
});

it('stores the failure so the thread still explains itself after a refresh', function (): void {
    fakeChatAgent(SupportAgent::class, [
        fn(): never => throw new RuntimeException('Provider exploded'),
    ]);

    invokeChat(SupportAgent::class, 'Anything');

    [, $messages] = chatTables();
    $error = $messages->find()->where(['role' => 'error'])->firstOrFail();

    expect($error->get('content'))->toBe('Provider exploded')
        ->and($error->get('metadata')['exception_class'])->toBe(RuntimeException::class)
        ->and($error->get('metadata')['stack_trace'])->toBeString();
});

it('still closes the stream cleanly when the agent fails', function (): void {
    fakeChatAgent(SupportAgent::class, [
        fn(): never => throw new RuntimeException('Boom'),
    ]);

    $result = invokeChat(SupportAgent::class, 'Anything');

    expect(array_slice(chatPartTypes($result['parts']), -2))->toBe(['error', 'data-ai-nexus-end']);

    $written = $result['emitter']->written();

    expect(end($written))->toBe('[DONE]');
});

it('surfaces an exception thrown inside a developer tool', function (): void {
    fakeChatAgent(FlakyToolAgent::class, [
        new ToolCall(id: 'call_1', name: 'BrokenLedgerTool', arguments: ['entry' => '42']),
    ]);

    $result = invokeChat(FlakyToolAgent::class, 'Look up entry 42');

    [, $messages] = chatTables();

    expect(chatPart($result['parts'], 'error')['errorText'])->toBe('Ledger service unavailable')
        ->and($messages->find()->where(['role' => 'error'])->firstOrFail()->get('metadata')['exception_class'])
        ->toBe(RuntimeException::class);
});

it('closes out a tool invocation the exception left hanging', function (): void {
    fakeChatAgent(FlakyToolAgent::class, [
        new ToolCall(id: 'call_1', name: 'BrokenLedgerTool', arguments: ['entry' => '42']),
    ]);

    invokeChat(FlakyToolAgent::class, 'Look up entry 42');

    [, , $invocations] = chatTables();
    $invocation = $invocations->find()->firstOrFail();

    expect($invocation->get('status'))->toBe('error')
        ->and($invocation->get('error'))->toBe('Ledger service unavailable')
        ->and($invocation->get('finished_at'))->not->toBeNull();
});

it('leaves the conversation usable after a failure', function (): void {
    fakeChatAgent(SupportAgent::class, fn(string $prompt): string => str_contains($prompt, 'fails')
        ? throw new RuntimeException('Transient blip')
        : 'Recovered fine.');

    $conversationId = chatConversationId(
        invokeChat(SupportAgent::class, 'This one fails')['parts'],
    );

    invokeChat(SupportAgent::class, 'This one works', $conversationId);

    [, $messages] = chatTables();

    expect($messages->find()->where(['role' => 'assistant'])->firstOrFail()->get('content'))
        ->toBe('Recovered fine.');
});

it('records a tool call from pending through to success', function (): void {
    fakeChatAgent(SupportAgent::class, [
        new ToolCall(id: 'call_1', name: 'SearchProductsTool', arguments: ['query' => 'hoodie']),
        'Found three matches.',
    ]);

    invokeChat(SupportAgent::class, 'Find me a hoodie');

    [, , $invocations] = chatTables();
    $invocation = $invocations->find()->firstOrFail();

    expect($invocation->get('type'))->toBe('tool')
        ->and($invocation->get('name'))->toBe('SearchProductsTool')
        ->and($invocation->get('arguments'))->toBe(['query' => 'hoodie'])
        ->and($invocation->get('status'))->toBe('success')
        ->and($invocation->get('result'))->toContain('Sony WH-1000')
        ->and($invocation->get('started_at'))->not->toBeNull()
        ->and($invocation->get('finished_at'))->not->toBeNull()
        ->and($invocation->get('duration_ms'))->toBeGreaterThanOrEqual(0);
});

it('attaches the row to the assistant turn it produced', function (): void {
    fakeChatAgent(SupportAgent::class, [
        new ToolCall(id: 'call_1', name: 'SearchProductsTool', arguments: ['query' => 'hoodie']),
        'Found three matches.',
    ]);

    invokeChat(SupportAgent::class, 'Find me a hoodie');

    [, $messages, $invocations] = chatTables();
    $assistant = $messages->find()->where(['role' => 'assistant'])->firstOrFail();

    expect($invocations->find()->firstOrFail()->get('message_id'))->toBe($assistant->get('id'));
});

it('records one row per call when a turn uses several tools', function (): void {
    $responses = [
        new ToolCall(id: 'call_1', name: 'SearchProductsTool', arguments: ['query' => 'hoodie']),
        new ToolCall(id: 'call_2', name: 'SearchProductsTool', arguments: ['query' => 'scarf']),
        'Found both.',
    ];
    $calls = 0;
    fakeChatAgent(TwoToolAgent::class, function () use (&$calls, $responses): mixed {
        $response = $responses[$calls] ?? end($responses);
        $calls++;

        if ($calls === 2) {
            // Keep the two invocation rows in different milliseconds: ids are
            // time-sortable uuid7 and started_at persists with second precision,
            // so same-ms rows would order randomly on a fast database.
            usleep(5000);
        }

        return $response;
    });

    invokeChat(TwoToolAgent::class, 'Find a hoodie and a scarf');

    [, , $invocations] = chatTables();
    $rows = $invocations->find()->orderBy(['started_at' => 'ASC', 'id' => 'ASC'])->all()->toList();

    expect($rows)->toHaveCount(2)
        ->and(array_column(array_map(fn($row) => $row->get('arguments'), $rows), 'query'))
        ->toBe(['hoodie', 'scarf'])
        ->and(array_unique(array_map(fn($row) => $row->get('status'), $rows)))->toBe(['success']);
});

it('emits the tool parts on the stream', function (): void {
    fakeChatAgent(SupportAgent::class, [
        new ToolCall(id: 'call_1', name: 'SearchProductsTool', arguments: ['query' => 'hoodie']),
        'Found three matches.',
    ]);

    $types = chatPartTypes(invokeChat(SupportAgent::class, 'Find me a hoodie')['parts']);

    expect($types)->toContain('tool-input-available')
        ->toContain('tool-output-available')
        ->and(array_search('tool-input-available', $types, true))
        ->toBeLessThan(array_search('tool-output-available', $types, true));
});

it('feeds prior turns back to a conversational agent', function (): void {
    fakeChatAgent(SupportAgent::class, ['First answer.', 'Second answer.']);

    $captured = [];
    $listener = watchStreamedHistory($captured);

    try {
        $conversationId = chatConversationId(
            invokeChat(SupportAgent::class, 'What is your return policy?')['parts'],
        );

        invokeChat(SupportAgent::class, 'And for sale items?', $conversationId);
    } finally {
        stopWatchingStreamedHistory($listener);
    }

    expect($captured[0])->toBe([])
        ->and($captured[1])->toHaveCount(2)
        ->and($captured[1][0]->content)->toBe('What is your return policy?')
        ->and($captured[1][1]->content)->toBe('First answer.');
});

it('sends a stateless agent only the current message', function (): void {
    fakeChatAgent(WeatherAgent::class, ['Sunny.', 'Still sunny.']);

    $captured = [];
    $listener = watchStreamedHistory($captured);

    try {
        $conversationId = chatConversationId(
            invokeChat(WeatherAgent::class, 'Weather in Tbilisi?')['parts'],
        );

        invokeChat(WeatherAgent::class, 'And tomorrow?', $conversationId);
    } finally {
        stopWatchingStreamedHistory($listener);
    }

    expect($captured[0])->toBe([])
        ->and($captured[1])->toBe([]);
});

it('never wraps a stateless agent', function (): void {
    fakeChatAgent(WeatherAgent::class, ['Clear skies.']);

    $wrapped = null;
    $listener = function (StreamingAgent $event) use (&$wrapped): void {
        $wrapped = $event->prompt->agent instanceof AiNexusConversationalAgent;
    };
    EventManager::instance()->on(StreamingAgent::eventName(), $listener);

    try {
        invokeChat(WeatherAgent::class, 'Weather?');
    } finally {
        stopWatchingStreamedHistory($listener);
    }

    expect($wrapped)->toBeFalse();
});

it('runs on the model the send asks for', function (): void {
    fakeChatAgent(SupportAgent::class, ['Answered elsewhere.']);

    $model = null;
    $listener = function (StreamingAgent $event) use (&$model): void {
        $model = $event->prompt->model;
    };
    EventManager::instance()->on(StreamingAgent::eventName(), $listener);

    try {
        invokeChat(SupportAgent::class, 'Try another model', null, [], 'gpt-5-mini');
    } finally {
        stopWatchingStreamedHistory($listener);
    }

    expect($model)->toBe('gpt-5-mini');
});

it('uses the agent own model when no override is given', function (): void {
    fakeChatAgent(SupportAgent::class, ['Answered normally.']);

    $model = null;
    $listener = function (StreamingAgent $event) use (&$model): void {
        $model = $event->prompt->model;
    };
    EventManager::instance()->on(StreamingAgent::eventName(), $listener);

    try {
        invokeChat(SupportAgent::class, 'Just answer');
    } finally {
        stopWatchingStreamedHistory($listener);
    }

    expect($model)->toBe('gpt-5.6-luna');
});

it('records the model that actually ran', function (): void {
    fakeChatAgent(SupportAgent::class, ['Answered elsewhere.']);

    invokeChat(SupportAgent::class, 'Try another model', null, [], 'gpt-5-mini');

    [, $messages] = chatTables();

    expect($messages->find()->where(['role' => 'assistant'])->firstOrFail()->get('meta')['model'])
        ->toBe('gpt-5-mini');
});

it('announces the provider the run resolved to', function (): void {
    fakeChatAgent(SupportAgent::class, ['Answered.']);

    $parts = invokeChat(SupportAgent::class, 'Hello')['parts'];

    expect(chatPart($parts, 'data-ai-nexus-provider')['data']['provider'])->toBe('openai');
});

it('announces the provider before any tool part', function (): void {
    fakeChatAgent(SupportAgent::class, [
        new ToolCall(id: 'call_1', name: 'SearchProductsTool', arguments: ['query' => 'hoodie']),
        'Found three matches.',
    ]);

    $types = chatPartTypes(invokeChat(SupportAgent::class, 'Find me a hoodie')['parts']);
    $toolTypes = array_values(array_filter(
        $types,
        fn(string $type): bool => str_starts_with($type, 'tool-') || $type === 'custom',
    ));

    expect($toolTypes)->not->toBeEmpty();

    $announced = array_search('data-ai-nexus-provider', $types, true);

    expect($announced)->not->toBeFalse();

    foreach ($types as $index => $type) {
        if (str_starts_with($type, 'tool-') || $type === 'custom') {
            expect($index)->toBeGreaterThan($announced);
        }
    }
});

it('names the provider that ran, not the one the agent is configured with', function (): void {
    fakeChatAgent(SupportAgent::class, ['Answered.']);

    $parts = invokeChat(SupportAgent::class, 'Hello')['parts'];

    [, $messages] = chatTables();
    $stored = $messages->find()->where(['role' => 'assistant'])->firstOrFail()->get('meta')['provider'] ?? null;

    expect(chatPart($parts, 'data-ai-nexus-provider')['data']['provider'])->toBe($stored);
});

it('answers structured output instead of throwing the streaming limitation', function (): void {
    fakeChatAgent(ExtractorAgent::class, [
        ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
    ]);

    $types = chatPartTypes(
        invokeChat(ExtractorAgent::class, 'Ada Lovelace, ada@example.com')['parts'],
    );

    expect($types)->not->toContain('error')
        ->and($types)->toContain('text-delta');
});

it('carries the structured payload to the browser', function (): void {
    fakeChatAgent(ExtractorAgent::class, [
        ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
    ]);

    $part = chatPart(
        invokeChat(ExtractorAgent::class, 'Ada Lovelace, ada@example.com')['parts'],
        'data-structured-output',
    );

    expect($part['data'])->toMatchArray(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
});

it('persists the structured turn like any other', function (): void {
    fakeChatAgent(ExtractorAgent::class, [
        ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
    ]);

    invokeChat(ExtractorAgent::class, 'Ada Lovelace, ada@example.com');

    [, $messages] = chatTables();
    $assistant = $messages->find()->where(['role' => 'assistant'])->firstOrFail();

    expect($assistant->get('content'))->toContain('Ada Lovelace')
        ->and($assistant->get('meta')['structured'])->toMatchArray(
            ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
        );
});

it('reports an unresolvable agent class as an error card', function (): void {
    $emitter = new StreamEmitter(echo: false);

    chatInvoker()->handle(
        new DiscoveredAgent('no.such.agent', 'NoSuchAgent', 'No\\Such\\Agent'),
        'Hello',
        null,
        $emitter,
    );

    $types = [];

    foreach ($emitter->written() as $payload) {
        if ($payload !== '[DONE]') {
            $types[] = (array)json_decode($payload, true);
        }
    }

    expect(chatPartTypes($types))->toContain('error')
        ->and($types[0]['type'])->toBe('data-ai-nexus-start');
});
