<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Chat;

use Cake\I18n\DateTime;
use Cake\Utility\Text;
use Crustum\Ai\Responses\AgentResponse;
use Crustum\Ai\Responses\Data\ToolCall;
use Crustum\Ai\Responses\Data\ToolResult;
use Crustum\Ai\Streaming\Event\Error;
use Crustum\Ai\Utility\Uuid;
use Crustum\AiNexus\Model\Entity\AiNexusConversation;
use Crustum\AiNexus\Model\Entity\AiNexusMessage;
use Crustum\AiNexus\Model\Table\AiNexusConversationsTable;
use Crustum\AiNexus\Model\Table\AiNexusMessagesTable;
use Throwable;

/**
 * Owns every write a chat turn makes.
 *
 * The user row is written before the agent is invoked, so a crash mid-stream
 * still leaves a readable thread rather than a question that vanished. Ids are
 * time-sortable uuid7 (see GAP-011), assigned here — the tables take no part
 * in id generation.
 */
class ConversationWriter
{
    /**
     * Longest a derived conversation title may be.
     */
    protected const int TITLE_LENGTH = 80;

    /**
     * Wire the writer with the tables chat turns persist to.
     *
     * @param \Crustum\AiNexus\Model\Table\AiNexusConversationsTable $conversations Conversations table.
     * @param \Crustum\AiNexus\Model\Table\AiNexusMessagesTable $messages Messages table.
     */
    public function __construct(
        protected AiNexusConversationsTable $conversations,
        protected AiNexusMessagesTable $messages,
    ) {
    }

    /**
     * Find the conversation for this turn, creating one when needed.
     *
     * @param string|null $conversationId Conversation id, if the turn continues one.
     * @param string $agentClass Agent class the turn runs against.
     * @param string $firstMessage The turn's message, used to title a new thread.
     * @return \Crustum\AiNexus\Model\Entity\AiNexusConversation
     */
    public function conversation(
        ?string $conversationId,
        string $agentClass,
        string $firstMessage,
    ): AiNexusConversation {
        if ($conversationId !== null) {
            $existing = $this->conversations->find()
                ->where(['id' => $conversationId])
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $conversation = $this->conversations->newEntity([
            'agent_class' => $agentClass,
            'title' => Text::truncate($firstMessage, self::TITLE_LENGTH, ['ellipsis' => '...', 'exact' => true]),
        ]);
        $conversation->set('id', Uuid::v7());

        return $this->conversations->saveOrFail($conversation);
    }

    /**
     * Store the message the developer just sent.
     *
     * @param \Crustum\AiNexus\Model\Entity\AiNexusConversation $conversation Conversation the turn belongs to.
     * @param string $content Message text.
     * @param array<int, array<string, mixed>> $attachments SDK file serializations.
     * @return \Crustum\AiNexus\Model\Entity\AiNexusMessage
     */
    public function storeUserMessage(
        AiNexusConversation $conversation,
        string $content,
        array $attachments = [],
    ): AiNexusMessage {
        return $this->store($conversation, 'user', content: $content, attachments: $attachments);
    }

    /**
     * Store a completed assistant turn.
     *
     * Tool calls and results are persisted in the SDK's own `toArray()` shapes
     * so `MessageHistory` can rehydrate them with the SDK's own `fromArray()`.
     * The `usage` JSON keeps the SDK's own `input_tokens` / `output_tokens`
     * shape untouched; only the promoted `prompt_tokens` / `completion_tokens`
     * columns carry the per-message counts, for the cheap SQL aggregates the
     * history list runs.
     *
     * @param \Crustum\AiNexus\Model\Entity\AiNexusConversation $conversation Conversation the turn belongs to.
     * @param \Crustum\Ai\Responses\AgentResponse $response Completed turn.
     * @param int $durationMs Wall time the turn took.
     * @param string|null $reasoning Reasoning gathered from the stream, if any.
     * @param array<string, mixed>|null $structured Parsed structured payload, if any.
     * @return \Crustum\AiNexus\Model\Entity\AiNexusMessage
     */
    public function storeAssistantMessage(
        AiNexusConversation $conversation,
        AgentResponse $response,
        int $durationMs,
        ?string $reasoning = null,
        ?array $structured = null,
    ): AiNexusMessage {
        return $this->store(
            $conversation,
            'assistant',
            content: $response->text,
            toolCalls: $response->toolCalls->map(
                static fn(ToolCall $call): array => $call->toArray(),
            )->toList(),
            toolResults: $response->toolResults->map(
                static fn(ToolResult $result): array => $result->toArray(),
            )->toList(),
            usage: $response->usage->toArray(),
            promptTokens: $response->usage->inputTokens,
            completionTokens: $response->usage->outputTokens,
            durationMs: $durationMs,
            meta: array_filter([
                ...$response->meta->toArray(),
                'reasoning' => $reasoning,
                'structured' => $structured,
            ], static fn(mixed $value): bool => $value !== null),
        );
    }

    /**
     * Store an error that occurred while running the agent.
     *
     * Every failure lands here — provider errors, tool exceptions, resolution
     * failures — so the thread always explains itself.
     *
     * @param \Crustum\AiNexus\Model\Entity\AiNexusConversation $conversation Conversation the turn belongs to.
     * @param \Throwable $e Failure that ended the turn.
     * @return \Crustum\AiNexus\Model\Entity\AiNexusMessage
     */
    public function storeError(AiNexusConversation $conversation, Throwable $e): AiNexusMessage
    {
        $detail = ProviderErrorDetail::for($e);

        return $this->store($conversation, 'error', content: $e->getMessage(), metadata: [
            'exception_class' => $e::class,
            'stack_trace' => $e->getTraceAsString(),
            'response_status' => $detail['status'] ?? null,
            'response_body' => $detail['body'] ?? null,
        ]);
    }

    /**
     * Store a mid-stream error event, which carries no exception of its own.
     *
     * @param \Crustum\AiNexus\Model\Entity\AiNexusConversation $conversation Conversation the turn belongs to.
     * @param string $message Error text.
     * @param bool $recoverable Whether the stream continued after the error.
     * @return \Crustum\AiNexus\Model\Entity\AiNexusMessage
     */
    public function storeStreamError(
        AiNexusConversation $conversation,
        string $message,
        bool $recoverable,
    ): AiNexusMessage {
        return $this->store($conversation, 'error', content: $message, metadata: [
            'exception_class' => Error::class,
            'recoverable' => $recoverable,
        ]);
    }

    /**
     * Insert a message row and mark the conversation as active.
     *
     * @param \Crustum\AiNexus\Model\Entity\AiNexusConversation $conversation Conversation the turn belongs to.
     * @param string $role Message role.
     * @param string|null $content Message text.
     * @param array<int, mixed> $attachments SDK file serializations.
     * @param array<int, mixed> $toolCalls SDK tool call shapes.
     * @param array<int, mixed> $toolResults SDK tool result shapes.
     * @param array<string, mixed> $usage SDK usage shape.
     * @param int|null $promptTokens Promoted input token count.
     * @param int|null $completionTokens Promoted output token count.
     * @param int|null $durationMs Wall time the turn took.
     * @param array<string, mixed> $meta Response meta plus reasoning/structured extras.
     * @param array<string, mixed> $metadata Error detail.
     * @return \Crustum\AiNexus\Model\Entity\AiNexusMessage
     */
    protected function store(
        AiNexusConversation $conversation,
        string $role,
        ?string $content = null,
        array $attachments = [],
        array $toolCalls = [],
        array $toolResults = [],
        array $usage = [],
        ?int $promptTokens = null,
        ?int $completionTokens = null,
        ?int $durationMs = null,
        array $meta = [],
        array $metadata = [],
    ): AiNexusMessage {
        $message = $this->messages->newEntity([
            'conversation_id' => $conversation->get('id'),
            'role' => $role,
            'content' => $content,
            'attachments' => $attachments,
            'tool_calls' => $toolCalls,
            'tool_results' => $toolResults,
            'usage' => $usage,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'duration_ms' => $durationMs,
            'meta' => $meta,
            'metadata' => $metadata,
        ]);
        $message->set('id', Uuid::v7());

        $stored = $this->messages->saveOrFail($message);

        $conversation->set('updated_at', DateTime::now());
        $this->conversations->saveOrFail($conversation);

        return $stored;
    }
}
