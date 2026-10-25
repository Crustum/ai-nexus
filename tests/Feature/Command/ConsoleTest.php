<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\AiNexus\AiNexus;
use Crustum\AiNexus\Command\InstallCommand;
use Crustum\AiNexus\Manifest\AiNexusManifest;

beforeEach(function (): void {
    useTestAppAgents();
});

function seedCommandConversation(string $id, string $title): void
{
    [$conversations] = chatTables();

    $conversation = $conversations->newEntity(['agent_class' => 'TestAgent', 'title' => $title]);
    $conversation->set('id', $id);

    $conversations->saveOrFail($conversation);
}

function backdateCommandConversation(string $id, string $modifier): void
{
    [$conversations] = chatTables();
    $conversations->updateAll(['updated_at' => new DateTimeImmutable($modifier)], ['id' => $id]);
}

it('prunes conversations older than the retention window', function (): void {
    seedCommandConversation('c0000000-0000-0000-0000-000000000001', 'old');
    seedCommandConversation('c0000000-0000-0000-0000-000000000002', 'new');
    backdateCommandConversation('c0000000-0000-0000-0000-000000000001', '-30 days');

    test()->exec('ai_nexus prune --days 7');
    test()->assertExitCode(0);
    test()->assertOutputContains('Pruned 1 conversation(s) older than 7 day(s).');

    [$conversations] = chatTables();

    expect($conversations->exists(['id' => 'c0000000-0000-0000-0000-000000000001']))->toBeFalse()
        ->and($conversations->exists(['id' => 'c0000000-0000-0000-0000-000000000002']))->toBeTrue();
});

it('prunes with the configured retention window when no option is given', function (): void {
    Configure::write('AiNexus.retention.days', 30);

    try {
        seedCommandConversation('c0000000-0000-0000-0000-000000000001', 'old');
        seedCommandConversation('c0000000-0000-0000-0000-000000000002', 'new');
        backdateCommandConversation('c0000000-0000-0000-0000-000000000001', '-40 days');

        test()->exec('ai_nexus prune');
        test()->assertExitCode(0);
        test()->assertOutputContains('Pruned 1 conversation(s) older than 30 day(s).');

        [$conversations] = chatTables();

        expect($conversations->exists(['id' => 'c0000000-0000-0000-0000-000000000001']))->toBeFalse()
            ->and($conversations->exists(['id' => 'c0000000-0000-0000-0000-000000000002']))->toBeTrue();
    } finally {
        Configure::write('AiNexus.retention.days', 7);
    }
});

it('clears all conversations', function (): void {
    seedCommandConversation('c0000000-0000-0000-0000-000000000001', 'x');
    seedCommandConversation('c0000000-0000-0000-0000-000000000002', 'y');

    test()->exec('ai_nexus clear');
    test()->assertExitCode(0);
    test()->assertOutputContains('Cleared 2 AiNexus conversation(s).');

    [$conversations] = chatTables();

    expect($conversations->find()->count())->toBe(0);
});

it('cascades clearing to messages and tool invocations', function (): void {
    seedCommandConversation('c0000000-0000-0000-0000-000000000001', 'x');

    [, $messages, $invocations] = chatTables();

    $message = $messages->newEntity([
        'conversation_id' => 'c0000000-0000-0000-0000-000000000001',
        'role' => 'user',
        'content' => 'hi',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
        'metadata' => [],
    ]);
    $message->set('id', 'm0000000-0000-0000-0000-000000000001');

    $messages->saveOrFail($message);

    $invocation = $invocations->newEntity([
        'conversation_id' => 'c0000000-0000-0000-0000-000000000001',
        'invocation_id' => 'inv-1',
        'tool_invocation_id' => 'ti-1',
        'type' => 'tool',
        'name' => 'searchProducts',
        'arguments' => [],
        'status' => 'success',
    ]);
    $invocation->set('id', 't0000000-0000-0000-0000-000000000001');

    $invocations->saveOrFail($invocation);

    test()->exec('ai_nexus clear');
    test()->assertExitCode(0);

    expect($messages->find()->count())->toBe(0)
        ->and($invocations->find()->count())->toBe(0);
});

it('registers the ai_nexus console commands', function (): void {
    test()->exec('ai_nexus prune --help');
    test()->assertExitCode(0);
    test()->assertOutputContains('--days');

    test()->exec('ai_nexus clear --help');
    test()->assertExitCode(0);

    test()->exec('ai_nexus status --help');
    test()->assertExitCode(0);
});

it('reports configuration and discovered agents', function (): void {
    test()->exec('ai_nexus status');
    test()->assertExitCode(0);
    test()->assertOutputContains('AiNexus ' . AiNexus::version());
    test()->assertOutputContains('Path: /ai_nexus');
    test()->assertOutputContains('test-app.ai.agents.support-agent');
    test()->assertOutputContains('SupportAgent');
});

it('prints failure reasons for unavailable agents', function (): void {
    test()->exec('ai_nexus status');
    test()->assertExitCode(0);
    test()->assertOutputContains('BrokenAgent');
    test()->assertOutputContains('UnresolvableDependency');
});

it('ships no install command', function (): void {
    expect(class_exists(InstallCommand::class))->toBeFalse();
});

it('publishes config, migrations, and bootstrap entries through the manifest', function (): void {
    $operations = AiNexusManifest::manifest();

    $migrations = array_values(array_filter(
        $operations,
        fn(array $operation): bool => ($operation['tag'] ?? null) === 'migrations',
    ));

    expect($migrations)->toHaveCount(1)
        ->and($migrations[0]['source'])->toEndWith('config' . DIRECTORY_SEPARATOR . 'Migrations');

    $configs = array_values(array_filter(
        $operations,
        fn(array $operation): bool => ($operation['tag'] ?? null) === 'config'
            && str_ends_with((string)($operation['source'] ?? ''), 'ai_nexus.php'),
    ));

    expect($configs)->toHaveCount(1)
        ->and($configs[0]['type'])->toBe('copy-safe')
        ->and($configs[0]['destination'])->toEndWith('ai_nexus.php');

    $webroots = array_values(array_filter(
        $operations,
        fn(array $operation): bool => str_ends_with((string)($operation['source'] ?? ''), 'frontend'),
    ));

    expect($webroots)->toHaveCount(1)
        ->and($webroots[0]['destination'])->toEndWith('ai_nexus-frontend');
});
