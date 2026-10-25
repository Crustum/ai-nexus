<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Test\Fixtures;

use Closure;
use Crustum\Ai\Approvals\Decisions;
use Crustum\Ai\Contracts\Agent;
use Crustum\Ai\Contracts\AgentInput;
use Crustum\Ai\Enums\Lab;
use Crustum\Ai\Messages\UserMessage;
use Crustum\Ai\Responses\AgentResponse;
use Crustum\Ai\Responses\QueuedAgentResponse;
use Crustum\Ai\Responses\StreamableAgentResponse;
use RuntimeException;
use Stringable;

/**
 * Minimal agent double for discovery detail tests.
 */
class FakeSubAgent implements Agent
{
    /**
     * Describe the agent behavior.
     *
     * @return \Stringable|string
     */
    public function instructions(): Stringable|string
    {
        return 'You are a helpful sub-agent fixture.';
    }

    /**
     * Invoke the agent synchronously.
     *
     * @param \Crustum\Ai\Contracts\AgentInput|\Crustum\Ai\Messages\UserMessage|\Crustum\Ai\Approvals\Decisions|string $prompt User prompt.
     * @param array $attachments Optional attachments.
     * @param \Crustum\Ai\Enums\Lab|array|string|null $provider Provider override.
     * @param string|null $model Model override.
     * @param int|null $timeout Request timeout.
     * @return \Crustum\Ai\Responses\AgentResponse
     * @throws \RuntimeException Always.
     */
    public function prompt(
        AgentInput|UserMessage|Decisions|string $prompt,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null,
    ): AgentResponse {
        throw new RuntimeException('Not implemented in tests.');
    }

    /**
     * Invoke the agent with a streaming response.
     *
     * @param \Crustum\Ai\Contracts\AgentInput|\Crustum\Ai\Messages\UserMessage|\Crustum\Ai\Approvals\Decisions|string $prompt User prompt.
     * @param array $attachments Optional attachments.
     * @param \Crustum\Ai\Enums\Lab|array|string|null $provider Provider override.
     * @param string|null $model Model override.
     * @param int|null $timeout Request timeout.
     * @return \Crustum\Ai\Responses\StreamableAgentResponse
     * @throws \RuntimeException Always.
     */
    public function stream(
        AgentInput|UserMessage|Decisions|string $prompt,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null,
        ?Closure $stopCheck = null,
    ): StreamableAgentResponse {
        throw new RuntimeException('Not implemented in tests.');
    }

    /**
     * Queue the agent invocation.
     *
     * @param \Crustum\Ai\Contracts\AgentInput|\Crustum\Ai\Messages\UserMessage|\Crustum\Ai\Approvals\Decisions|string $prompt User prompt.
     * @param array $attachments Optional attachments.
     * @param \Crustum\Ai\Enums\Lab|array|string|null $provider Provider override.
     * @param string|null $model Model override.
     * @return \Crustum\Ai\Responses\QueuedAgentResponse
     * @throws \RuntimeException Always.
     */
    public function queue(
        AgentInput|UserMessage|Decisions|string $prompt,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null,
    ): QueuedAgentResponse {
        throw new RuntimeException('Not implemented in tests.');
    }

    /**
     * Broadcast the streamed events.
     *
     * @param \Crustum\Ai\Contracts\AgentInput|\Crustum\Ai\Messages\UserMessage|\Crustum\Ai\Approvals\Decisions|string $prompt User prompt.
     * @param mixed $channels Broadcast channels.
     * @param array $attachments Optional attachments.
     * @param bool $now Whether to broadcast immediately.
     * @param \Crustum\Ai\Enums\Lab|array|string|null $provider Provider override.
     * @param string|null $model Model override.
     * @return \Crustum\Ai\Responses\StreamableAgentResponse
     * @throws \RuntimeException Always.
     */
    public function broadcast(
        AgentInput|UserMessage|Decisions|string $prompt,
        mixed $channels,
        array $attachments = [],
        bool $now = false,
        Lab|array|string|null $provider = null,
        ?string $model = null,
    ): StreamableAgentResponse {
        throw new RuntimeException('Not implemented in tests.');
    }

    /**
     * Broadcast the streamed events immediately.
     *
     * @param \Crustum\Ai\Contracts\AgentInput|\Crustum\Ai\Messages\UserMessage|\Crustum\Ai\Approvals\Decisions|string $prompt User prompt.
     * @param mixed $channels Broadcast channels.
     * @param array $attachments Optional attachments.
     * @param \Crustum\Ai\Enums\Lab|array|string|null $provider Provider override.
     * @param string|null $model Model override.
     * @return \Crustum\Ai\Responses\StreamableAgentResponse
     * @throws \RuntimeException Always.
     */
    public function broadcastNow(
        AgentInput|UserMessage|Decisions|string $prompt,
        mixed $channels,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null,
    ): StreamableAgentResponse {
        throw new RuntimeException('Not implemented in tests.');
    }

    /**
     * Queue the agent and broadcast the streamed events.
     *
     * @param \Crustum\Ai\Contracts\AgentInput|\Crustum\Ai\Messages\UserMessage|\Crustum\Ai\Approvals\Decisions|string $prompt User prompt.
     * @param mixed $channels Broadcast channels.
     * @param array $attachments Optional attachments.
     * @param \Crustum\Ai\Enums\Lab|array|string|null $provider Provider override.
     * @param string|null $model Model override.
     * @return \Crustum\Ai\Responses\QueuedAgentResponse
     * @throws \RuntimeException Always.
     */
    public function broadcastOnQueue(
        AgentInput|UserMessage|Decisions|string $prompt,
        mixed $channels,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null,
    ): QueuedAgentResponse {
        throw new RuntimeException('Not implemented in tests.');
    }
}
