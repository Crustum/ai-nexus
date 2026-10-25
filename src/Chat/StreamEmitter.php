<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Chat;

use Crustum\Ai\Responses\Data\TextUsage;
use Crustum\Ai\Streaming\Event\Error;
use Crustum\Ai\Streaming\Event\StreamEnd;
use Crustum\Ai\Streaming\Event\StreamEvent;
use Crustum\Ai\Streaming\Event\StreamStart;
use Crustum\Ai\Streaming\Event\ToolCall;
use Crustum\Ai\Streaming\Event\ToolResult;
use Crustum\AiNexus\AiNexus;
use Throwable;

/**
 * Writes the SSE stream the dashboard consumes, in the Vercel AI SDK UI message
 * protocol.
 *
 * Model parts come from the SDK's own mapping (`AiNexusStreamProtocol`), so the
 * wire format stays the SDK's. AiNexus emits them itself rather than returning
 * the SDK's protocol response because a debugging tool needs its own parts on
 * the same stream: the conversation announcement, the live provider name,
 * notices, and the closing completion data.
 *
 * Two upstream gaps are closed by the newer SDK rather than worked around:
 *
 * - `ProviderToolEvent` has a first-class mapping (`custom` with the raw
 *   payload in `providerMetadata`), so no raw-event part is needed.
 * - `ToolResult` maps `successful: false` to `tool-output-error` itself, so no
 *   failure-preserving branch is needed.
 *
 * Everything else mirrors the SDK protocol generator: one `start` part, tool
 * results without a preceding call are dropped, and the `finish` part is held
 * back until the very end.
 */
class StreamEmitter
{
    /**
     * Whether the single `start` part was already written.
     *
     * @var bool
     */
    protected bool $started = false;

    /**
     * Tool call ids seen on the stream, used to drop orphaned results.
     *
     * @var array<string, true>
     */
    protected array $toolCalls = [];

    /**
     * Finish reason held back until the stream closes.
     *
     * @var string|null
     */
    protected ?string $finishReason = null;

    /**
     * Usage accumulated across steps for the held-back finish part.
     *
     * @var \Crustum\Ai\Responses\Data\TextUsage|null
     */
    protected ?TextUsage $finishUsage = null;

    /**
     * Captured output, used when no real output stream is available (tests).
     *
     * @var list<string>
     */
    protected array $written = [];

    /**
     * Wire the emitter with its output mode, SAPI, and event mapping.
     *
     * @param bool $echo Whether to echo parts as they are written.
     * @param string $sapi Injectable only so the flush decision can be tested; production always passes the real SAPI.
     * @param \Crustum\AiNexus\Chat\AiNexusStreamProtocol|null $protocol Event mapping.
     */
    public function __construct(
        protected bool $echo = true,
        protected string $sapi = PHP_SAPI,
        protected ?AiNexusStreamProtocol $protocol = null,
    ) {
        $this->protocol ??= new AiNexusStreamProtocol();
    }

    /**
     * Whether each part should be pushed to the client as it is written.
     *
     * Deliberately *not* `headers_sent()`. The stock php.ini sets
     * `output_buffering=4096`, so the first echo lands in PHP's own buffer and
     * the headers are never sent — a `headers_sent()` check therefore answers
     * "no" on every part for the whole run, and the dashboard sits blank until
     * the agent finishes and then paints the entire conversation at once.
     * Silent, and only on real deployments.
     *
     * The honest question is whether this is being served over HTTP at all.
     * Under CLI it is a test harness, which calls the controller inside its own
     * output buffer and reads the body back — flushing there would push our
     * bytes past that buffer to stdout and hand the caller an empty response.
     * The rule lives on `AiNexus` so the dashboard and this emitter can never
     * disagree about it.
     *
     * @return bool
     */
    public function shouldFlush(): bool
    {
        return AiNexus::flushesUnder($this->sapi);
    }

    /**
     * Announce the conversation before any model output, so the client can put
     * the id in the URL immediately — a refresh mid-stream still finds the thread.
     *
     * @param string $conversationId Conversation id.
     * @param string $userMessageId User message id.
     * @return void
     */
    public function conversationStarted(string $conversationId, string $userMessageId): void
    {
        $this->part([
            'type' => 'data-ai-nexus-start',
            'data' => [
                'conversationId' => $conversationId,
                'userMessageId' => $userMessageId,
            ],
        ]);
    }

    /**
     * Translate one SDK stream event into zero or more protocol parts.
     *
     * @param \Crustum\Ai\Streaming\Event\StreamEvent $event Stream event.
     * @return void
     */
    public function event(StreamEvent $event): void
    {
        if ($event instanceof StreamStart) {
            if ($this->started) {
                return;
            }

            $this->started = true;
        }

        if ($event instanceof ToolCall) {
            $this->toolCalls[$event->toolCall->id] = true;
        }

        if ($event instanceof ToolResult && !isset($this->toolCalls[$event->toolResult->id])) {
            return;
        }

        if ($event instanceof StreamEnd) {
            $this->finishReason = $event->reason;
            $this->finishUsage = $this->finishUsage?->add($event->usage) ?? $event->usage;

            return;
        }

        $part = $this->protocol->map($event);

        if ($part !== null && $part !== []) {
            $this->part($part);
        }
    }

    /**
     * Emit a whole answer as one text block.
     *
     * Used for structured-output agents, which the SDK refuses to stream, so a
     * single `prompt()` result still reaches the client through the same pipe.
     *
     * @param string $text Answer text.
     * @param string $messageId Message id.
     * @return void
     */
    public function text(string $text, string $messageId): void
    {
        if (!$this->started) {
            $this->started = true;
            $this->part(['type' => 'start', 'messageId' => $messageId]);
        }

        $this->part(['type' => 'text-start', 'id' => $messageId]);
        $this->part(['type' => 'text-delta', 'id' => $messageId, 'delta' => $text]);
        $this->part(['type' => 'text-end', 'id' => $messageId]);
    }

    /**
     * Emit the structured payload of a `HasStructuredOutput` agent.
     *
     * @param array<string, mixed> $structured Parsed structured payload.
     * @return void
     */
    public function structured(array $structured): void
    {
        $this->part(['type' => 'data-structured-output', 'data' => $structured]);
    }

    /**
     * Report a failed run inline, as a part rather than an HTTP status.
     *
     * By the time the stream closure runs the response headers are already sent,
     * so throwing here would truncate the stream with no explanation. This is
     * the only way an error can reach the developer.
     *
     * @param \Throwable $e Failure that ended the run.
     * @param string $messageId Error message id.
     * @return void
     */
    public function error(Throwable $e, string $messageId): void
    {
        $detail = ProviderErrorDetail::for($e);

        $this->part([
            'type' => 'error',
            'errorText' => $e->getMessage(),
            'data' => [
                'messageId' => $messageId,
                'exceptionClass' => $e::class,
                'stackTrace' => $e->getTraceAsString(),
                'responseStatus' => $detail['status'] ?? null,
                'responseBody' => $detail['body'] ?? null,
                'recoverable' => false,
            ],
        ]);
    }

    /**
     * Report a mid-stream error event, which carries no exception.
     *
     * @param \Crustum\Ai\Streaming\Event\Error $event Stream error event.
     * @param string $messageId Error message id.
     * @return void
     */
    public function streamError(Error $event, string $messageId): void
    {
        $this->part([
            'type' => 'error',
            'errorText' => $event->message,
            'data' => [
                'messageId' => $messageId,
                'exceptionClass' => Error::class,
                'stackTrace' => null,
                'responseStatus' => null,
                'responseBody' => null,
                'recoverable' => $event->recoverable,
            ],
        ]);
    }

    /**
     * Name the provider this run is actually going through.
     *
     * A provider-native tool card carries a `provider /` prefix, and on replay
     * that comes from the assistant message's stored meta — the provider the SDK
     * reported, not the one the agent was configured with. Those differ after a
     * failover, which is exactly when knowing who to blame matters, so the live
     * stream announces the real one rather than letting the client assume the
     * configured one. Re-announced per attempt, so a failover corrects it.
     *
     * @param string $provider Provider name.
     * @return void
     */
    public function provider(string $provider): void
    {
        $this->part([
            'type' => 'data-ai-nexus-provider',
            'data' => ['provider' => $provider],
        ]);
    }

    /**
     * Report an informational event that is not a failure — a provider failover,
     * for instance, where the run carried on against the next provider.
     *
     * @param string $message Notice text.
     * @param array<string, mixed> $data Notice data.
     * @return void
     */
    public function notice(string $message, array $data = []): void
    {
        $this->part([
            'type' => 'data-ai-nexus-notice',
            'data' => ['message' => $message, ...$data],
        ]);
    }

    /**
     * Close the stream: the held `finish` part, AiNexus's own completion data,
     * then the protocol terminator.
     *
     * @param string|null $assistantMessageId Assistant message id.
     * @param array<string, mixed>|null $usage Stored usage shape.
     * @param int|null $durationMs Wall time the turn took.
     * @return void
     */
    public function finish(
        ?string $assistantMessageId = null,
        ?array $usage = null,
        ?int $durationMs = null,
    ): void {
        if ($this->finishReason !== null) {
            $this->part($this->protocol->finishFor(
                $this->finishReason,
                $this->finishUsage ?? new TextUsage(),
            ));

            $this->finishReason = null;
            $this->finishUsage = null;
        }

        $this->part([
            'type' => 'data-ai-nexus-end',
            'data' => [
                'assistantMessageId' => $assistantMessageId,
                'usage' => $usage,
                'durationMs' => $durationMs,
            ],
        ]);

        $this->raw('[DONE]');
    }

    /**
     * Everything written so far, for tests and for the non-echoing path.
     *
     * @return list<string>
     */
    public function written(): array
    {
        return $this->written;
    }

    /**
     * Write one protocol part to the stream.
     *
     * @param array<string, mixed> $part Protocol part.
     * @return void
     */
    protected function part(array $part): void
    {
        $this->raw((string)json_encode($part));
    }

    /**
     * Write one payload to the stream, capturing it for tests first.
     *
     * @param string $payload SSE payload without the `data: ` framing.
     * @return void
     */
    protected function raw(string $payload): void
    {
        $this->written[] = $payload;

        if (!$this->echo) {
            return;
        }

        echo 'data: ' . $payload . "\n\n";

        if (!$this->shouldFlush()) {
            return;
        }

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
