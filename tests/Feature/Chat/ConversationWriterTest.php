<?php
declare(strict_types=1);

use Crustum\Ai\Responses\AgentResponse;
use Crustum\Ai\Responses\Data\Meta;
use Crustum\Ai\Responses\Data\TextUsage;
use Crustum\Ai\Streaming\Event\Error;
use Crustum\AiNexus\Chat\ConversationWriter;
use Crustum\AiNexus\Model\Entity\AiNexusConversation;
use Crustum\AiNexus\Model\Table\AiNexusConversationsTable;
use Crustum\AiNexus\Model\Table\AiNexusMessagesTable;

function testConversationWriter(): ConversationWriter
{
    $locator = test()->getTableLocator();

    return new ConversationWriter(
        $locator->get('AiNexusConversations', ['className' => AiNexusConversationsTable::class]),
        $locator->get('AiNexusMessages', ['className' => AiNexusMessagesTable::class]),
    );
}

function testConversation(string $title = 'Thread'): AiNexusConversation
{
    return testConversationWriter()->conversation(null, 'TestAgent', $title);
}

function fakeAgentResponse(string $text = 'Answer.'): AgentResponse
{
    return new AgentResponse('inv-1', $text, new TextUsage(10, 4), new Meta('openai', 'gpt-5.6-luna'));
}

it('creates a titled conversation for a new thread', function (): void {
    $conversation = testConversationWriter()->conversation(null, 'TestAgent', 'How do I reset a password?');

    expect($conversation->get('agent_class'))->toBe('TestAgent')
        ->and($conversation->get('title'))->toBe('How do I reset a password?')
        ->and((string)$conversation->get('id'))->not->toBeEmpty();
});

it('truncates a long first message into the title', function (): void {
    $conversation = testConversation(str_repeat('word ', 30));

    expect(strlen((string)$conversation->get('title')))->toBeLessThanOrEqual(83);
});

it('returns the existing conversation when one is given', function (): void {
    $writer = testConversationWriter();
    $conversation = $writer->conversation(null, 'TestAgent', 'First');

    expect($writer->conversation((string)$conversation->get('id'), 'TestAgent', 'Second')->get('id'))
        ->toBe($conversation->get('id'));
});

it('starts a new thread for an unknown conversation id', function (): void {
    $conversation = testConversationWriter()->conversation('missing-id', 'TestAgent', 'First');

    expect((string)$conversation->get('id'))->not->toBe('missing-id');
});

it('stores the user message with its attachments', function (): void {
    $conversation = testConversation();
    $attachments = [['type' => 'stored-image', 'name' => 'sky.png', 'path' => 'ai_nexus/sky']];

    $message = testConversationWriter()->storeUserMessage($conversation, 'Look', $attachments);

    expect($message->get('role'))->toBe('user')
        ->and($message->get('content'))->toBe('Look')
        ->and($message->get('attachments'))->toBe($attachments)
        ->and($message->get('conversation_id'))->toBe($conversation->get('id'));
});

it('stores the assistant turn with promoted tokens and full breakdown', function (): void {
    $message = testConversationWriter()->storeAssistantMessage(testConversation(), fakeAgentResponse(), 12);

    expect($message->get('role'))->toBe('assistant')
        ->and($message->get('content'))->toBe('Answer.')
        ->and($message->get('prompt_tokens'))->toBe(10)
        ->and($message->get('completion_tokens'))->toBe(4)
        ->and($message->get('duration_ms'))->toBe(12)
        ->and($message->get('usage'))->toBe([
            'input_tokens' => 10,
            'output_tokens' => 4,
            'cache_read_input_tokens' => null,
            'cache_write_input_tokens' => null,
            'reasoning_tokens' => null,
        ])
        ->and($message->get('meta'))->toMatchArray(['provider' => 'openai', 'model' => 'gpt-5.6-luna']);
});

it('keeps reasoning and structured output on the assistant row', function (): void {
    $message = testConversationWriter()->storeAssistantMessage(
        testConversation(),
        fakeAgentResponse(),
        5,
        reasoning: 'First I check the catalog.',
        structured: ['name' => 'Ada Lovelace'],
    );

    expect($message->get('meta')['reasoning'])->toBe('First I check the catalog.')
        ->and($message->get('meta')['structured'])->toBe(['name' => 'Ada Lovelace']);
});

it('leaves the meta clean for a run without reasoning', function (): void {
    $message = testConversationWriter()->storeAssistantMessage(testConversation(), fakeAgentResponse(), 5);

    expect($message->get('meta'))->not->toHaveKey('reasoning')
        ->and($message->get('meta'))->not->toHaveKey('structured');
});

it('stores failures so the thread explains itself', function (): void {
    $message = testConversationWriter()->storeError(testConversation(), new RuntimeException('Boom'));

    expect($message->get('role'))->toBe('error')
        ->and($message->get('content'))->toBe('Boom')
        ->and($message->get('metadata'))->toMatchArray(['exception_class' => RuntimeException::class]);
});

it('stores mid-stream errors with their recoverability', function (): void {
    $message = testConversationWriter()->storeStreamError(testConversation(), 'Wobbled.', true);

    expect($message->get('role'))->toBe('error')
        ->and($message->get('metadata'))->toMatchArray([
            'exception_class' => Error::class,
            'recoverable' => true,
        ]);
});

it('marks the conversation active on every write', function (): void {
    $writer = testConversationWriter();
    $conversation = $writer->conversation(null, 'TestAgent', 'First');

    [$conversations] = chatTables();
    $conversations->updateAll(
        ['updated_at' => new DateTimeImmutable('-1 day')],
        ['id' => (string)$conversation->get('id')],
    );

    $writer->storeUserMessage($conversation, 'Second');

    expect($conversations->get((string)$conversation->get('id'))->get('updated_at')->isToday())->toBeTrue();
});
