<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Event\EventManager;
use Crustum\Ai\Ai;
use Crustum\AiNexus\Chat\AgentInvoker;
use Crustum\AiNexus\Chat\AiNexusConversationalAgent;
use Crustum\AiNexus\Chat\AttachmentStore;
use Crustum\AiNexus\Chat\ConversationWriter;
use Crustum\AiNexus\Chat\MessageHistory;
use Crustum\AiNexus\Chat\StreamEmitter;
use Crustum\AiNexus\Chat\StrictAiNexusConversationalAgent;
use Crustum\AiNexus\Discovery\AgentSlug;
use Crustum\AiNexus\Discovery\DiscoveredAgent;
use Crustum\AiNexus\Model\Table\AiNexusConversationsTable;
use Crustum\AiNexus\Model\Table\AiNexusMessagesTable;
use Crustum\AiNexus\Model\Table\AiNexusToolInvocationsTable;
use Crustum\AiNexus\Test\Browser\BrowserTestCase;
use Crustum\AiNexus\Test\TestCase\AiNexusTestCase;
use League\Container\Container;
use League\Container\ReflectionContainer;
use TestApp\Middleware\DenyMiddleware;

uses(AiNexusTestCase::class)->in('Feature', 'Integration');

// Browser end-to-end tests: `composer test:e2e` (excluded from `composer test`).
uses(BrowserTestCase::class)->group('e2e')->in('Browser');

/**
 * Fake an agent's responses for the whole chat pipeline.
 *
 * Fake gateways key on the concrete class name, and the invoker hands the SDK
 * an `AiNexusConversationalAgent` wrapper for conversational agents — so faking
 * the agent class alone would leave the decorated call talking to a real
 * provider. Registering all three classes is what makes the fake stick
 * regardless of which path the invoker takes.
 *
 * @param array<int, mixed>|\Closure $responses Fake gateway responses.
 * @return void
 */
function fakeChatAgent(string $agent, Closure|array $responses = []): void
{
    foreach ([$agent, AiNexusConversationalAgent::class, StrictAiNexusConversationalAgent::class] as $class) {
        Ai::manager()->fakeAgent($class, $responses);
    }
}

/**
 * @return array{\Crustum\AiNexus\Model\Table\AiNexusConversationsTable, \Crustum\AiNexus\Model\Table\AiNexusMessagesTable, \Crustum\AiNexus\Model\Table\AiNexusToolInvocationsTable}
 */
function chatTables(): array
{
    $locator = test()->getTableLocator();

    return [
        $locator->get('AiNexusConversations', ['className' => AiNexusConversationsTable::class]),
        $locator->get('AiNexusMessages', ['className' => AiNexusMessagesTable::class]),
        $locator->get('AiNexusToolInvocations', ['className' => AiNexusToolInvocationsTable::class]),
    ];
}

/**
 * @return \Crustum\AiNexus\Chat\AgentInvoker
 */
function chatInvoker(): AgentInvoker
{
    [$conversations, $messages, $invocations] = chatTables();

    $container = new Container();
    $container->delegate(new ReflectionContainer());

    $attachments = new AttachmentStore();
    $writer = new ConversationWriter($conversations, $messages);
    $history = new MessageHistory($messages, $attachments);

    return new AgentInvoker(
        $writer,
        $history,
        $attachments,
        EventManager::instance(),
        $container,
        $invocations,
    );
}

/**
 * @return \Crustum\AiNexus\Discovery\DiscoveredAgent
 */
function discoveredChatAgent(string $class): DiscoveredAgent
{
    return new DiscoveredAgent(AgentSlug::make($class), (new ReflectionClass($class))->getShortName(), $class);
}

/**
 * Point discovery at the TestApp agents.
 *
 * @return void
 */
function useTestAppAgents(): void
{
    Configure::write('AiNexus.discovery.paths', [__DIR__ . '/TestApp/Ai/Agents']);
    Configure::write('AiNexus.discovery.ignore', []);
}

/**
 * Deny dashboard access for the test through host middleware.
 *
 * The plugin ships no gate of its own: access control belongs to the host
 * application, which applies its middleware through `AiNexus.ui.middleware`.
 * Tests stand in a denying middleware for the host stack.
 *
 * @return void
 */
function denyDashboard(): void
{
    Configure::write('AiNexus.ui.middleware', [DenyMiddleware::class]);
}

/**
 * Post a message to an agent and run the stream to completion.
 *
 * The turn runs when the stream body is read — after the streaming headers —
 * so reading the body through a passing negative assertion both executes the
 * turn and captures its bytes out of the output buffer. The result is the
 * decoded protocol parts.
 *
 * @param array<string, mixed> $extra Extra send payload.
 * @return array<int, array<string, mixed>>
 */
function sendMessage(string $slug, string $message, ?string $conversationId = null, array $extra = []): array
{
    $payload = array_filter([
        'message' => $message,
        'conversation_id' => $conversationId,
        ...$extra,
    ]);

    ob_start();
    test()->post("/ai_nexus/api/chat/{$slug}/send", $payload);
    test()->assertResponseOk();
    test()->assertResponseNotContains('ai-nexus-never-emitted-marker');

    $raw = (string)ob_get_clean();

    return decodeSseParts($raw);
}

/**
 * Decode a raw SSE body into protocol parts.
 *
 * @return array<int, array<string, mixed>>
 */
function decodeSseParts(string $raw): array
{
    $parts = [];

    foreach (explode("\n\n", $raw) as $chunk) {
        $chunk = trim(str_replace('data: ', '', $chunk));

        if ($chunk === '' || $chunk === '[DONE]') {
            continue;
        }

        $parts[] = (array)json_decode($chunk, true);
    }

    return $parts;
}

/**
 * Run one chat turn to completion and return the stream it wrote.
 *
 * @param array<int, \Crustum\Ai\Files\File> $attachments SDK file objects for this turn.
 * @return array{emitter: \Crustum\AiNexus\Chat\StreamEmitter, parts: array<int, array<string, mixed>>}
 */
function invokeChat(
    string $agentClass,
    string $message,
    ?string $conversationId = null,
    array $attachments = [],
    ?string $model = null,
): array {
    $emitter = new StreamEmitter(echo: false);

    chatInvoker()->handle(
        discoveredChatAgent($agentClass),
        $message,
        $conversationId,
        $emitter,
        $attachments,
        $model,
    );

    $parts = [];

    foreach ($emitter->written() as $payload) {
        if ($payload === '[DONE]') {
            continue;
        }

        $parts[] = (array)json_decode($payload, true);
    }

    return ['emitter' => $emitter, 'parts' => $parts];
}

/**
 * @param array<int, array<string, mixed>> $parts Decoded stream parts.
 * @return array<int, string>
 */
function chatPartTypes(array $parts): array
{
    return array_column($parts, 'type');
}

/**
 * @param array<int, array<string, mixed>> $parts Decoded stream parts.
 * @return array<string, mixed>|null
 */
function chatPart(array $parts, string $type): ?array
{
    foreach ($parts as $part) {
        if (($part['type'] ?? null) === $type) {
            return $part;
        }
    }

    return null;
}

/**
 * @param array<int, array<string, mixed>> $parts Decoded stream parts.
 * @return string
 */
function chatConversationId(array $parts): string
{
    return chatPart($parts, 'data-ai-nexus-start')['data']['conversationId'];
}
