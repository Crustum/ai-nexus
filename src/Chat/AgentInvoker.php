<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Chat;

use Cake\Event\EventManagerInterface;
use Cake\I18n\DateTime;
use Crustum\Ai\Contracts\Agent;
use Crustum\Ai\Contracts\Conversational;
use Crustum\Ai\Contracts\HasStructuredOutput;
use Crustum\Ai\Event\AgentFailedOver;
use Crustum\Ai\Event\InvokingTool;
use Crustum\Ai\Event\StreamingAgent;
use Crustum\Ai\Event\ToolInvoked;
use Crustum\Ai\Responses\AgentResponse;
use Crustum\Ai\Responses\StreamedAgentResponse;
use Crustum\Ai\Responses\StructuredAgentResponse;
use Crustum\Ai\Streaming\Event\Error;
use Crustum\Ai\Streaming\Event\ProviderToolEvent;
use Crustum\Ai\Streaming\Event\ReasoningDelta;
use Crustum\AiNexus\Discovery\DiscoveredAgent;
use Crustum\AiNexus\Model\Entity\AiNexusConversation;
use Crustum\AiNexus\Model\Entity\AiNexusMessage;
use Crustum\AiNexus\Model\Table\AiNexusToolInvocationsTable;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Throwable;

/**
 * Runs one chat turn end to end: history, invocation, streaming, persistence.
 *
 * The whole pipeline sits inside a single `catch (Throwable)`. That is not
 * belt-and-braces — it is the entire error strategy. The SDK lets exceptions
 * propagate: the tool runner wraps the developer's tool handler with no
 * `catch`, and the generation loop reports every tool result as successful, so
 * there is no "failed tool result" path to observe. A tool that throws exits
 * `stream()` entirely, and only a catch-all this wide can turn that into a
 * readable card.
 *
 * Listeners are registered on the injected event manager per invocation and
 * detached in a `finally`, so one turn's recorder can never observe another
 * turn's run — including across tests sharing the global manager.
 */
class AgentInvoker
{
    /**
     * Wire one chat turn with its persistence, history, uploads, events,
     * agent container, and tool invocation rows.
     *
     * @param \Crustum\AiNexus\Chat\ConversationWriter $writer Chat turn persistence.
     * @param \Crustum\AiNexus\Chat\MessageHistory $history Thread rehydration.
     * @param \Crustum\AiNexus\Chat\AttachmentStore $attachments Upload storage.
     * @param \Cake\Event\EventManagerInterface $events Event manager the SDK dispatches to.
     * @param \Psr\Container\ContainerInterface $container Container used to build agents.
     * @param \Crustum\AiNexus\Model\Table\AiNexusToolInvocationsTable $toolInvocations Tool invocations table.
     */
    public function __construct(
        protected ConversationWriter $writer,
        protected MessageHistory $history,
        protected AttachmentStore $attachments,
        protected EventManagerInterface $events,
        protected ContainerInterface $container,
        protected AiNexusToolInvocationsTable $toolInvocations,
    ) {
    }

    /**
     * Invoke the agent and write the turn to the stream.
     *
     * @param \Crustum\AiNexus\Discovery\DiscoveredAgent $discovered Agent to run.
     * @param string $message Developer message.
     * @param string|null $conversationId Conversation id, if the turn continues one.
     * @param \Crustum\AiNexus\Chat\StreamEmitter $emitter Stream the turn is written to.
     * @param array<int, \Crustum\Ai\Files\File> $attachments SDK file objects for this turn.
     * @param string|null $model Model override, skipping the agent's own resolution.
     * @return void
     */
    public function handle(
        DiscoveredAgent $discovered,
        string $message,
        ?string $conversationId,
        StreamEmitter $emitter,
        array $attachments = [],
        ?string $model = null,
    ): void {
        $conversation = null;
        $invocationId = null;
        $listeners = [];

        try {
            $conversation = $this->writer->conversation($conversationId, $discovered->class, $message);

            $history = $this->history->for((string)$conversation->get('id'));

            $userMessage = $this->writer->storeUserMessage(
                $conversation,
                $message,
                $this->attachments->toArray($attachments),
            );

            $emitter->conversationStarted(
                (string)$conversation->get('id'),
                (string)$userMessage->get('id'),
            );

            $agent = $this->container->get($discovered->class);

            if (!$agent instanceof Agent) {
                throw new RuntimeException($discovered->class . ' does not implement ' . Agent::class . '.');
            }

            $this->announceProvider($emitter, $listeners);
            $this->announceFailovers($emitter, $listeners);
            $this->recordToolInvocations($conversation, $listeners);

            if ($agent instanceof HasStructuredOutput) {
                $assistant = $this->promptOnce($agent, $message, $conversation, $emitter, $invocationId, $attachments, $model);
            } else {
                $assistant = $this->stream($agent, $history, $message, $conversation, $emitter, $invocationId, $attachments, $model);
            }

            if ($assistant instanceof AiNexusMessage && $invocationId !== null) {
                (new AiNexusRecorder($this->toolInvocations, (string)$conversation->get('id')))
                    ->linkToMessage($invocationId, (string)$assistant->get('id'));
            }

            $emitter->finish(
                $assistant instanceof AiNexusMessage ? (string)$assistant->get('id') : null,
                $assistant?->get('usage'),
                $assistant?->get('duration_ms'),
            );
        } catch (Throwable $throwable) {
            $this->fail($throwable, $conversation, $invocationId, $emitter);
        } finally {
            $this->detachListeners($listeners);
        }
    }

    /**
     * Detach per-invocation listeners, so one turn's recorder can never
     * observe another turn's run.
     *
     * @param array<int, array{string, callable}> $listeners Registered listeners.
     * @return void
     */
    protected function detachListeners(array $listeners): void
    {
        foreach ($listeners as [$name, $listener]) {
            $this->events->off($name, $listener);
        }
    }

    /**
     * Stream a turn, emitting protocol parts as the events arrive.
     *
     * @param \Crustum\Ai\Contracts\Agent $agent Agent to run.
     * @param array<int, \Crustum\Ai\Messages\Message> $history Thread history.
     * @param string $message Developer message.
     * @param \Crustum\AiNexus\Model\Entity\AiNexusConversation $conversation Conversation the turn belongs to.
     * @param \Crustum\AiNexus\Chat\StreamEmitter $emitter Stream the turn is written to.
     * @param string|null $invocationId Run invocation id, filled in for the caller.
     * @param array<int, \Crustum\Ai\Files\File> $attachments SDK file objects for this turn.
     * @param string|null $model Model override.
     * @return \Crustum\AiNexus\Model\Entity\AiNexusMessage|null
     */
    protected function stream(
        Agent $agent,
        array $history,
        string $message,
        AiNexusConversation $conversation,
        StreamEmitter $emitter,
        ?string &$invocationId,
        array $attachments = [],
        ?string $model = null,
    ): ?AiNexusMessage {
        $target = $agent instanceof Conversational
            ? AiNexusConversationalAgent::for($agent, $history)
            : $agent;

        $startedAt = hrtime(true);
        $assistant = null;
        $reasoning = new ReasoningBuffer();

        $response = $target->stream($message, $attachments, model: $model);

        $invocationId = $response->invocationId;

        $response->withinConversation((string)$conversation->get('id'))
            ->then(function (StreamedAgentResponse $streamed) use ($conversation, $startedAt, &$assistant, $reasoning): void {
                $assistant = $this->writer->storeAssistantMessage(
                    $conversation,
                    $streamed,
                    $this->elapsedMs($startedAt),
                    reasoning: $reasoning->text(),
                );
            });

        $providerTools = new ProviderToolRecorder(
            $this->toolInvocations,
            (string)$conversation->get('id'),
        );

        foreach ($response as $event) {
            if ($event instanceof ReasoningDelta) {
                $reasoning->append($event->delta);
            }

            if ($event instanceof ProviderToolEvent) {
                $providerTools->record($event, $response->invocationId);

                $emitter->event($event);

                continue;
            }

            if ($event instanceof Error) {
                $errorMessage = $this->writer->storeStreamError(
                    $conversation,
                    $event->message,
                    $event->recoverable,
                );

                $emitter->streamError($event, (string)$errorMessage->get('id'));

                continue;
            }

            $emitter->event($event);
        }

        return $assistant;
    }

    /**
     * Run a structured-output agent in one shot.
     *
     * The SDK refuses to stream any agent implementing `HasStructuredOutput`,
     * so these never take the streaming path. They are also invoked
     * undecorated: the decorator cannot carry `HasStructuredOutput` (it would
     * break every conversational stream), and no agent is usefully both today.
     *
     * @param \Crustum\Ai\Contracts\Agent $agent Agent to run.
     * @param string $message Developer message.
     * @param \Crustum\AiNexus\Model\Entity\AiNexusConversation $conversation Conversation the turn belongs to.
     * @param \Crustum\AiNexus\Chat\StreamEmitter $emitter Stream the turn is written to.
     * @param string|null $invocationId Run invocation id, filled in for the caller.
     * @param array<int, \Crustum\Ai\Files\File> $attachments SDK file objects for this turn.
     * @param string|null $model Model override.
     * @return \Crustum\AiNexus\Model\Entity\AiNexusMessage
     */
    protected function promptOnce(
        Agent $agent,
        string $message,
        AiNexusConversation $conversation,
        StreamEmitter $emitter,
        ?string &$invocationId,
        array $attachments = [],
        ?string $model = null,
    ): AiNexusMessage {
        $startedAt = hrtime(true);

        $response = $agent->prompt($message, $attachments, model: $model);

        $invocationId = $response->invocationId;

        $assistant = $this->writer->storeAssistantMessage(
            $conversation,
            $response,
            $this->elapsedMs($startedAt),
            structured: $response instanceof StructuredAgentResponse
                ? $response->structured
                : null,
        );

        $this->emitPrompted($response, $emitter);

        return $assistant;
    }

    /**
     * Push a non-streamed response through the same protocol the client already
     * understands, so one reader serves both transports.
     *
     * @param \Crustum\Ai\Responses\AgentResponse $response Completed turn.
     * @param \Crustum\AiNexus\Chat\StreamEmitter $emitter Stream the turn is written to.
     * @return void
     */
    protected function emitPrompted(AgentResponse $response, StreamEmitter $emitter): void
    {
        $emitter->text($response->text, $response->invocationId);

        if ($response instanceof StructuredAgentResponse) {
            $emitter->structured($response->structured);
        }
    }

    /**
     * Record the failure, close out anything left hanging, and tell the browser.
     *
     * @param \Throwable $e Failure that ended the turn.
     * @param \Crustum\AiNexus\Model\Entity\AiNexusConversation|null $conversation Conversation the turn belongs to, if one exists.
     * @param string|null $invocationId Run invocation id, if the agent was reached.
     * @param \Crustum\AiNexus\Chat\StreamEmitter $emitter Stream the turn is written to.
     * @return void
     */
    protected function fail(
        Throwable $e,
        ?AiNexusConversation $conversation,
        ?string $invocationId,
        StreamEmitter $emitter,
    ): void {
        $messageId = '';

        if ($conversation instanceof AiNexusConversation) {
            $messageId = (string)$this->writer->storeError($conversation, $e)->get('id');
        }

        $this->failDanglingInvocations($invocationId, $e);

        $emitter->error($e, $messageId);
        $emitter->finish();
    }

    /**
     * A tool that throws fired `InvokingTool` but never `ToolInvoked`, leaving
     * its row pending forever. Close those out so the card shows failed rather
     * than spinning.
     *
     * @param string|null $invocationId Run invocation id, if the agent was reached.
     * @param \Throwable $e Failure that ended the turn.
     * @return void
     */
    protected function failDanglingInvocations(?string $invocationId, Throwable $e): void
    {
        if ($invocationId === null) {
            return;
        }

        $this->toolInvocations->updateAll(
            [
                'status' => 'error',
                'error' => $e->getMessage(),
                'finished_at' => DateTime::now(),
            ],
            ['invocation_id' => $invocationId, 'status' => 'pending'],
        );
    }

    /**
     * Write a row for every tool the run invokes.
     *
     * Registered per invocation because a row belongs to a conversation, and a
     * globally-registered listener would have to guess which one.
     *
     * @param \Crustum\AiNexus\Model\Entity\AiNexusConversation $conversation Conversation the turn belongs to.
     * @param array<int, array{string, callable}> $listeners Registered listeners, for detachment.
     * @return void
     */
    protected function recordToolInvocations(AiNexusConversation $conversation, array &$listeners): void
    {
        $recorder = new AiNexusRecorder($this->toolInvocations, (string)$conversation->get('id'));

        $invoking = function (InvokingTool $event) use ($recorder): void {
            $recorder->toolInvoking($event);
        };
        $invoked = function (ToolInvoked $event) use ($recorder): void {
            $recorder->toolInvoked($event);
        };

        $this->events->on(InvokingTool::eventName(), $invoking);
        $this->events->on(ToolInvoked::eventName(), $invoked);

        $listeners[] = [InvokingTool::eventName(), $invoking];
        $listeners[] = [ToolInvoked::eventName(), $invoked];
    }

    /**
     * Surface a provider failover as information, not as a failure — the run
     * carried on against the next configured provider.
     *
     * @param \Crustum\AiNexus\Chat\StreamEmitter $emitter Stream the turn is written to.
     * @param array<int, array{string, callable}> $listeners Registered listeners, for detachment.
     * @return void
     */
    protected function announceFailovers(StreamEmitter $emitter, array &$listeners): void
    {
        $listener = function (AgentFailedOver $event) use ($emitter): void {
            $emitter->notice(
                sprintf(
                    'Failed over from %s (%s): %s',
                    $event->provider->name(),
                    $event->model,
                    $event->exception->getMessage(),
                ),
                ['provider' => $event->provider->name(), 'model' => $event->model],
            );
        };

        $this->events->on(AgentFailedOver::eventName(), $listener);

        $listeners[] = [AgentFailedOver::eventName(), $listener];
    }

    /**
     * Tell the client which provider the run is actually going through.
     *
     * The client needs this before the first provider-native tool event, which
     * is why it rides the stream rather than being read off the agent's
     * configuration: `StreamingAgent` fires once the SDK has resolved the
     * provider, and again on a failover retry, so the prefix on a live tool card
     * names the same provider the stored message will.
     *
     * @param \Crustum\AiNexus\Chat\StreamEmitter $emitter Stream the turn is written to.
     * @param array<int, array{string, callable}> $listeners Registered listeners, for detachment.
     * @return void
     */
    protected function announceProvider(StreamEmitter $emitter, array &$listeners): void
    {
        $listener = function (StreamingAgent $event) use ($emitter): void {
            $emitter->provider($event->prompt->provider()->name());
        };

        $this->events->on(StreamingAgent::eventName(), $listener);

        $listeners[] = [StreamingAgent::eventName(), $listener];
    }

    /**
     * Whole milliseconds elapsed since the given `hrtime(true)` value.
     *
     * @param float|int $startedAt `hrtime(true)` value from before the run.
     * @return int
     */
    protected function elapsedMs(float|int $startedAt): int
    {
        return (int)round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
