<?php
declare(strict_types=1);

use Cake\Datasource\ConnectionManager;
use Crustum\AiNexus\Test\Support\QueryCounter;
use TestApp\Ai\Agents\SupportAgent;

function seedConversations(int $conversations, int $messagesEach = 4, int $toolsEach = 3): void
{
    [$conversationsTable, $messagesTable, $invocationsTable] = chatTables();

    for ($c = 0; $c < $conversations; $c++) {
        $conversation = $conversationsTable->newEntity([
            'agent_class' => SupportAgent::class,
            'title' => "Conversation {$c}",
        ]);
        $conversation->set('id', sprintf('c0000000-0000-0000-0000-%012d', $c));
        $conversationsTable->saveOrFail($conversation);

        for ($m = 0; $m < $messagesEach; $m++) {
            $message = $messagesTable->newEntity([
                'conversation_id' => $conversation->get('id'),
                'role' => $m % 2 === 0 ? 'user' : 'assistant',
                'content' => "Message {$m} of conversation {$c}",
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'attachments' => [],
                'tool_calls' => [],
                'tool_results' => [],
                'usage' => [],
                'meta' => ['provider' => 'openai', 'model' => 'gpt-5.6-luna'],
                'metadata' => [],
            ]);
            $message->set('id', sprintf('m%01d%03d000-0000-0000-0000-%08d', $c, $m, $m));
            $messagesTable->saveOrFail($message);

            for ($t = 0; $t < $toolsEach; $t++) {
                $invocation = $invocationsTable->newEntity([
                    'conversation_id' => $conversation->get('id'),
                    'message_id' => $message->get('id'),
                    'invocation_id' => "inv_{$c}_{$m}",
                    'tool_invocation_id' => "call_{$c}_{$m}_{$t}",
                    'type' => 'tool',
                    'name' => 'SearchProductsTool',
                    'arguments' => ['query' => 'hoodie'],
                    'result' => ['found' => 3],
                    'status' => 'success',
                    'started_at' => new DateTimeImmutable(),
                    'finished_at' => new DateTimeImmutable(),
                ]);
                $invocation->set('id', sprintf('t%01d%03d%03d-0000-0000-0000-%04d', $c, $m, $t, $t));
                $invocationsTable->saveOrFail($invocation);
            }
        }
    }
}

function queriesFor(Closure $callback): int
{
    $driver = ConnectionManager::get('test')->getDriver();
    $counter = new QueryCounter();
    $driver->setLogger($counter);
    $driver->enableQueryLogging(true);

    try {
        $callback();
    } finally {
        $driver->enableQueryLogging(false);
    }

    return $counter->count;
}

it('costs the same number of queries however long the history is', function (): void {
    seedConversations(3);
    $small = queriesFor(function (): void {
        test()->get('/ai_nexus/api/conversations');
        test()->assertResponseOk();
    });

    seedConversations(25);
    $large = queriesFor(function (): void {
        test()->get('/ai_nexus/api/conversations');
        test()->assertResponseOk();
    });

    expect($large)->toBe($small);
});

it('costs the same number of queries however many messages a conversation has', function (): void {
    seedConversations(1, messagesEach: 2, toolsEach: 1);

    [$conversations] = chatTables();
    $short = $conversations->find()->firstOrFail()->get('id');
    $small = queriesFor(function () use ($short): void {
        test()->get("/ai_nexus/api/conversations/{$short}");
        test()->assertResponseOk();
    });

    $conversations->deleteAll([]);

    seedConversations(1, messagesEach: 30, toolsEach: 5);

    $long = $conversations->find()->firstOrFail()->get('id');
    $large = queriesFor(function () use ($long): void {
        test()->get("/ai_nexus/api/conversations/{$long}");
        test()->assertResponseOk();
    });

    expect($large)->toBe($small);
});

it('costs the same number of queries however many filter options exist', function (): void {
    seedConversations(3);
    $small = queriesFor(function (): void {
        test()->get('/ai_nexus/api/conversations?status=success&search=Conversation');
        test()->assertResponseOk();
    });

    seedConversations(25);
    $large = queriesFor(function (): void {
        test()->get('/ai_nexus/api/conversations?status=success&search=Conversation');
        test()->assertResponseOk();
    });

    expect($large)->toBe($small);
});

it('keeps the whole dashboard read within a small fixed budget', function (): void {
    seedConversations(25);

    expect(queriesFor(function (): void {
        test()->get('/ai_nexus/api/conversations');
        test()->assertResponseOk();
    }))->toBeLessThanOrEqual(12);

    [$conversations] = chatTables();
    $id = $conversations->find()->firstOrFail()->get('id');

    expect(queriesFor(function () use ($id): void {
        test()->get("/ai_nexus/api/conversations/{$id}");
        test()->assertResponseOk();
    }))->toBeLessThanOrEqual(12);
});
