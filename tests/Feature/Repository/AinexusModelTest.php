<?php
declare(strict_types=1);

use Cake\Datasource\ConnectionManager;
use TestApp\Ai\Agents\SupportAgent;

it('creates the ainexus tables', function (): void {
    $schema = ConnectionManager::get('test')->getSchemaCollection();

    expect($schema->listTables())->toContain('ainexus_conversations')
        ->and($schema->listTables())->toContain('ainexus_messages')
        ->and($schema->listTables())->toContain('ainexus_tool_invocations');
});

it('persists a conversation with messages and tool invocations', function (): void {
    [$conversations, $messages, $invocations] = chatTables();

    $conversation = $conversations->newEntity([
        'agent_class' => SupportAgent::class,
        'title' => 'Wireless headphones under $200',
    ]);
    $conversation->set('id', 'c0000000-0000-0000-0000-000000000001');

    $conversations->saveOrFail($conversation);

    $message = $messages->newEntity([
        'conversation_id' => 'c0000000-0000-0000-0000-000000000001',
        'role' => 'assistant',
        'content' => 'Here are some options',
        'attachments' => [],
        'tool_calls' => [['id' => 't1', 'name' => 'searchProducts']],
        'tool_results' => [],
        'usage' => ['input_tokens' => 340, 'output_tokens' => 128],
        'meta' => ['provider' => 'openai', 'model' => 'gpt-5.6-luna'],
        'metadata' => [],
        'prompt_tokens' => 340,
        'completion_tokens' => 128,
    ]);
    $message->set('id', 'm0000000-0000-0000-0000-000000000001');

    $messages->saveOrFail($message);

    $invocation = $invocations->newEntity([
        'conversation_id' => 'c0000000-0000-0000-0000-000000000001',
        'invocation_id' => 'inv-1',
        'tool_invocation_id' => 'ti-1',
        'type' => 'tool',
        'name' => 'searchProducts',
        'arguments' => ['query' => 'headphones'],
        'result' => ['ok' => true],
        'status' => 'success',
        'started_at' => new DateTimeImmutable(),
        'finished_at' => new DateTimeImmutable(),
    ]);
    $invocation->set('id', 't0000000-0000-0000-0000-000000000001');

    $invocations->saveOrFail($invocation);

    expect($messages->find()->where(['conversation_id' => 'c0000000-0000-0000-0000-000000000001'])->count())
        ->toBe(1)
        ->and($invocations->find()->where(['conversation_id' => 'c0000000-0000-0000-0000-000000000001'])->count())
        ->toBe(1)
        ->and($messages->get('m0000000-0000-0000-0000-000000000001')->get('tool_calls'))->toBeArray()
        ->and($messages->get('m0000000-0000-0000-0000-000000000001')->get('tool_calls')[0]['name'])
        ->toBe('searchProducts')
        ->and($messages->get('m0000000-0000-0000-0000-000000000001')->get('usage')['output_tokens'])
        ->toBe(128);
});
