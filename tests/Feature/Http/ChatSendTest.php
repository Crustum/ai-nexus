<?php
declare(strict_types=1);

use Crustum\Ai\Ai;
use TestApp\Ai\Agents\SupportAgent;

beforeEach(function (): void {
    Ai::manager()->resetFakeState();
    useTestAppAgents();
});

it('streams an answer as event-stream parts', function (): void {
    fakeChatAgent(SupportAgent::class, ['Certainly, here is the answer.']);

    $parts = sendMessage('test-app.ai.agents.support-agent', 'How do I reset a password?');

    test()->assertHeaderContains('Content-Type', 'text/event-stream');

    expect(chatPartTypes($parts))
        ->toContain('data-ai-nexus-start')
        ->toContain('start')
        ->toContain('text-start')
        ->toContain('text-delta')
        ->toContain('finish')
        ->toContain('data-ai-nexus-end');
});

it('announces the conversation before any model output', function (): void {
    fakeChatAgent(SupportAgent::class, ['Hi.']);

    $parts = sendMessage('test-app.ai.agents.support-agent', 'Hello');
    $first = $parts[0];

    [$conversations] = chatTables();

    expect($first['type'])->toBe('data-ai-nexus-start')
        ->and($first['data']['conversationId'])->toBe($conversations->find()->first()->get('id'))
        ->and($first['data']['userMessageId'])->not->toBeEmpty();
});

it('holds the finish part until the end of the stream', function (): void {
    fakeChatAgent(SupportAgent::class, ['Done.']);

    $types = chatPartTypes(sendMessage('test-app.ai.agents.support-agent', 'Anything else?'));

    expect(array_slice($types, -2))->toBe(['finish', 'data-ai-nexus-end']);
});

it('rejects a message for an unknown agent', function (): void {
    test()->post('/ai_nexus/api/chat/nope.not.here/send', ['message' => 'Hello']);
    test()->assertResponseCode(404);
});

it('requires a message', function (): void {
    test()->post('/ai_nexus/api/chat/test-app.ai.agents.support-agent/send', []);
    test()->assertResponseCode(422);
});

it('is closed when host middleware denies', function (): void {
    denyDashboard();

    test()->post('/ai_nexus/api/chat/test-app.ai.agents.support-agent/send', ['message' => 'Hello']);
    test()->assertResponseCode(403);
});

it('keeps running after the browser disconnects', function (): void {
    ignore_user_abort(false);

    fakeChatAgent(SupportAgent::class, ['Finished regardless.']);

    sendMessage('test-app.ai.agents.support-agent', 'Start and walk away');

    expect(ignore_user_abort())->toBe(1);

    ignore_user_abort(false);
});
