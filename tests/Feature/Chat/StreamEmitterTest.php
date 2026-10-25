<?php
declare(strict_types=1);

use Crustum\Ai\Responses\Data\TextUsage;
use Crustum\Ai\Responses\Data\ToolCall as ToolCallData;
use Crustum\Ai\Responses\Data\ToolResult as ToolResultData;
use Crustum\Ai\Streaming\Event\Error;
use Crustum\Ai\Streaming\Event\ProviderToolEvent;
use Crustum\Ai\Streaming\Event\ReasoningDelta;
use Crustum\Ai\Streaming\Event\StreamEnd;
use Crustum\Ai\Streaming\Event\StreamStart;
use Crustum\Ai\Streaming\Event\TextDelta;
use Crustum\Ai\Streaming\Event\TextEnd;
use Crustum\Ai\Streaming\Event\TextStart;
use Crustum\Ai\Streaming\Event\ToolCall;
use Crustum\Ai\Streaming\Event\ToolResult;
use Crustum\AiNexus\AiNexus;
use Crustum\AiNexus\Chat\StreamEmitter;

function emitterParts(StreamEmitter $emitter): array
{
    $parts = [];

    foreach ($emitter->written() as $payload) {
        if ($payload !== '[DONE]') {
            $parts[] = (array)json_decode($payload, true);
        }
    }

    return $parts;
}

function streamStartEvent(): StreamStart
{
    return new StreamStart('evt-start', 'openai', 'gpt-5.6-luna', time(), []);
}

function streamEndEvent(string $reason = 'stop'): StreamEnd
{
    return new StreamEnd('evt-end', $reason, new TextUsage(10, 4), time());
}

it('flushes on a web SAPI even though the headers are not sent yet', function (): void {
    expect(headers_sent())->toBeFalse()
        ->and((new StreamEmitter(sapi: 'fpm-fcgi'))->shouldFlush())->toBeTrue();
});

it('flushes under every SAPI that serves HTTP', function (): void {
    foreach (['fpm-fcgi', 'cli-server', 'apache2handler', 'litespeed', 'frankenphp'] as $sapi) {
        expect((new StreamEmitter(sapi: $sapi))->shouldFlush())->toBeTrue("SAPI {$sapi}");
    }
});

it('never flushes under the CLI, where the caller owns the output buffer', function (): void {
    foreach (['cli', 'phpdbg', 'embed'] as $sapi) {
        expect((new StreamEmitter(sapi: $sapi))->shouldFlush())->toBeFalse("SAPI {$sapi}");
    }
});

it('defaults to the running SAPI', function (): void {
    expect((new StreamEmitter())->shouldFlush())->toBe(PHP_SAPI !== 'cli');
});

it('tells the dashboard the same thing it tells the emitter', function (): void {
    expect(AiNexus::streams())->toBe(AiNexus::flushesUnder(PHP_SAPI))
        ->and(AiNexus::scriptVariables()['streaming'])->toBe(AiNexus::streams());
});

it('reports no streaming under the test runner', function (): void {
    expect(AiNexus::streams())->toBeFalse();
})->skip(PHP_SAPI !== 'cli', 'Only meaningful under the CLI SAPI.');

it('still captures every part when flushing is off', function (): void {
    $emitter = new StreamEmitter(echo: false, sapi: 'cli');

    $emitter->text('Hello.', 'msg_1');

    expect(array_column(emitterParts($emitter), 'type'))->toBe(['start', 'text-start', 'text-delta', 'text-end']);
});

it('announces the conversation before any model output', function (): void {
    $emitter = new StreamEmitter(echo: false);

    $emitter->conversationStarted('conv-1', 'msg-1');

    expect(emitterParts($emitter)[0])->toBe([
        'type' => 'data-ai-nexus-start',
        'data' => ['conversationId' => 'conv-1', 'userMessageId' => 'msg-1'],
    ]);
});

it('maps model events through the SDK protocol', function (): void {
    $emitter = new StreamEmitter(echo: false);

    $emitter->event(streamStartEvent());
    $emitter->event(new TextStart('evt-ts', 'msg_1', time()));
    $emitter->event(new TextDelta('evt-td', 'msg_1', 'Hello.', time()));
    $emitter->event(new TextEnd('evt-te', 'msg_1', time()));

    expect(array_column(emitterParts($emitter), 'type'))
        ->toBe(['start', 'text-start', 'text-delta', 'text-end']);
});

it('emits exactly one start part even across steps', function (): void {
    $emitter = new StreamEmitter(echo: false);

    $emitter->event(streamStartEvent());
    $emitter->event(streamStartEvent());

    expect(array_count_values(array_column(emitterParts($emitter), 'type'))['start'])->toBe(1);
});

it('tracks tool calls and drops results without a call', function (): void {
    $emitter = new StreamEmitter(echo: false);

    $orphan = new ToolResult('evt-tr', new ToolResultData('call-x', 'Search', [], 'done'), true, null, time());
    $emitter->event($orphan);

    expect(emitterParts($emitter))->toBe([]);

    $call = new ToolCall('evt-tc', new ToolCallData('call_1', 'Search', []), time());
    $result = new ToolResult('evt-tr', new ToolResultData('call_1', 'Search', [], 'done'), true, null, time());
    $emitter->event($call);
    $emitter->event($result);

    expect(array_column(emitterParts($emitter), 'type'))
        ->toBe(['tool-input-available', 'tool-output-available']);
});

it('preserves a failed tool result instead of reporting success', function (): void {
    $emitter = new StreamEmitter(echo: false);

    $emitter->event(new ToolCall('evt-tc', new ToolCallData('call_1', 'Search', []), time()));
    $emitter->event(new ToolResult('evt-tr', new ToolResultData('call_1', 'Search', [], 'nope'), false, 'Timed out.', time()));

    $parts = emitterParts($emitter);

    expect($parts[1]['type'])->toBe('tool-output-error')
        ->and($parts[1]['errorText'])->toBe('Timed out.');
});

it('carries provider tool events through the SDK mapping', function (): void {
    $emitter = new StreamEmitter(echo: false);

    $emitter->event(new ProviderToolEvent('evt_1', 'item_1', 'web_search_call', [], 'completed', time(), 'openai'));

    $part = emitterParts($emitter)[0];

    expect($part['type'])->toBe('custom')
        ->and($part['providerMetadata']['openai']['itemId'])->toBe('item_1');
});

it('holds the finish part until the stream closes', function (): void {
    $emitter = new StreamEmitter(echo: false);

    $emitter->event(streamStartEvent());
    $emitter->event(new TextDelta('evt-td', 'msg_1', 'Hi.', time()));
    $emitter->event(streamEndEvent());

    expect(emitterParts($emitter))->toHaveCount(2);

    $emitter->finish('msg-2', ['input_tokens' => 10], 7);

    $types = array_column(emitterParts($emitter), 'type');

    expect(array_slice($types, -2))->toBe(['finish', 'data-ai-nexus-end'])
        ->and($emitter->written()[count($emitter->written()) - 1])->toBe('[DONE]');
});

it('accumulates usage across steps into one finish part', function (): void {
    $emitter = new StreamEmitter(echo: false);

    $emitter->event(streamStartEvent());
    $emitter->event(streamEndEvent('tool_calls'));
    $emitter->event(streamStartEvent());
    $emitter->event(streamEndEvent('stop'));
    $emitter->finish();

    $finish = chatPart(emitterParts($emitter), 'finish');

    expect($finish['messageMetadata']['usage']['inputTokens'])->toBe(20)
        ->and($finish['messageMetadata']['usage']['outputTokens'])->toBe(8);
});

it('closes the stream without a finish part when the run never started', function (): void {
    $emitter = new StreamEmitter(echo: false);

    $emitter->finish();

    $parts = emitterParts($emitter);

    expect($parts)->toHaveCount(1)
        ->and($parts[0]['type'])->toBe('data-ai-nexus-end');
});

it('emits a whole answer as one text block', function (): void {
    $emitter = new StreamEmitter(echo: false);

    $emitter->text('Hello.', 'msg_1');

    expect(array_column(emitterParts($emitter), 'type'))
        ->toBe(['start', 'text-start', 'text-delta', 'text-end'])
        ->and(emitterParts($emitter)[2]['delta'])->toBe('Hello.');
});

it('emits the structured payload for the JSON card', function (): void {
    $emitter = new StreamEmitter(echo: false);

    $emitter->structured(['name' => 'Ada Lovelace']);

    expect(emitterParts($emitter)[0])->toBe([
        'type' => 'data-structured-output',
        'data' => ['name' => 'Ada Lovelace'],
    ]);
});

it('reports a failed run inline with the provider detail', function (): void {
    $emitter = new StreamEmitter(echo: false);

    $emitter->error(new RuntimeException('Boom'), 'msg-err');

    $part = emitterParts($emitter)[0];

    expect($part['type'])->toBe('error')
        ->and($part['errorText'])->toBe('Boom')
        ->and($part['data'])->toMatchArray([
            'messageId' => 'msg-err',
            'exceptionClass' => RuntimeException::class,
            'recoverable' => false,
        ]);
});

it('reports a mid-stream error with its recoverability', function (): void {
    $emitter = new StreamEmitter(echo: false);

    $emitter->streamError(new Error('evt-e', 'stream', 'Wobbled.', true, time()), 'msg-err');

    $part = emitterParts($emitter)[0];

    expect($part['type'])->toBe('error')
        ->and($part['errorText'])->toBe('Wobbled.')
        ->and($part['data']['recoverable'])->toBeTrue();
});

it('names the provider and carries notices on dashboard parts', function (): void {
    $emitter = new StreamEmitter(echo: false);

    $emitter->provider('openai');
    $emitter->notice('Failed over.', ['provider' => 'anthropic']);

    expect(emitterParts($emitter))->toBe([
        ['type' => 'data-ai-nexus-provider', 'data' => ['provider' => 'openai']],
        ['type' => 'data-ai-nexus-notice', 'data' => ['message' => 'Failed over.', 'provider' => 'anthropic']],
    ]);
});

it('accumulates reasoning deltas outside the text', function (): void {
    $emitter = new StreamEmitter(echo: false);

    $emitter->event(new ReasoningDelta('evt-r', 'reasoning-1', 'Thinking.', time()));

    expect(emitterParts($emitter)[0]['type'])->toBe('reasoning-delta');
});
