<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Chat;

use Cake\I18n\DateTime;
use Crustum\Ai\Event\InvokingTool;
use Crustum\Ai\Event\ToolInvoked;
use Crustum\Ai\Tools\ToolNameResolver;
use Crustum\Ai\Utility\Uuid;
use Crustum\AiNexus\Model\Entity\AiNexusToolInvocation;
use Crustum\AiNexus\Model\Table\AiNexusToolInvocationsTable;
use DateTimeInterface;

/**
 * Turns the SDK's tool events into `ainexus_tool_invocations` rows.
 *
 * Two SDK facts shape everything here:
 *
 * 1. `ToolInvoked` fires **only on success**. The tool runner executes the
 *    handler with no `catch` around it, so a throwing tool leaves its row
 *    `pending` — the invocation-level catch-all sweeps those to `error`.
 * 2. The id correlating the two events is not reliable under nesting (see
 *    `resolveRow()`).
 *
 * The recorder is bound per invocation rather than globally: it needs the
 * conversation the run belongs to, and a listener registered for the lifetime
 * of one request would otherwise have to guess.
 */
class AiNexusRecorder
{
    /**
     * Bind the recorder to the conversation the run belongs to.
     *
     * @param \Crustum\AiNexus\Model\Table\AiNexusToolInvocationsTable $toolInvocations Tool invocations table.
     * @param string $conversationId Conversation the run belongs to.
     */
    public function __construct(
        protected AiNexusToolInvocationsTable $toolInvocations,
        protected string $conversationId,
    ) {
    }

    /**
     * A tool is about to run. The pending row is what gives the UI an in-flight
     * card for free — the developer sees the call the moment it happens rather
     * than after it returns.
     *
     * @param \Crustum\Ai\Event\InvokingTool $event Tool invocation event.
     * @return void
     */
    public function toolInvoking(InvokingTool $event): void
    {
        $row = $this->toolInvocations->newEntity([
            'conversation_id' => $this->conversationId,
            'invocation_id' => $event->invocationId,
            'tool_invocation_id' => $event->toolInvocationId,
            'type' => 'tool',
            'name' => ToolNameResolver::resolve($event->tool),
            'arguments' => $event->arguments,
            'status' => 'pending',
            'started_at' => DateTime::now(),
        ]);
        $row->set('id', Uuid::v7());

        $this->toolInvocations->saveOrFail($row);
    }

    /**
     * A tool returned. Nothing else reports success, so this is the only place
     * a row leaves `pending` on the happy path.
     *
     * @param \Crustum\Ai\Event\ToolInvoked $event Tool result event.
     * @return void
     */
    public function toolInvoked(ToolInvoked $event): void
    {
        $row = $this->resolveRow($event);

        if (!$row instanceof AiNexusToolInvocation) {
            return;
        }

        $finishedAt = DateTime::now();

        $row->set('status', 'success');
        $row->set('result', $event->result);
        $row->set('finished_at', $finishedAt);
        $row->set('duration_ms', self::durationMs($row->get('started_at'), $finishedAt));

        $this->toolInvocations->saveOrFail($row);
    }

    /**
     * Find the pending row this result belongs to.
     *
     * The obvious lookup — by `tool_invocation_id` — is not always right. The
     * SDK holds the current id in a single property on the *provider*, and the
     * manager caches provider instances by name. A sub-agent tool therefore
     * runs a nested invocation through the same provider and overwrites the id
     * before the outer tool's `ToolInvoked` fires, so the outer event carries
     * the inner tool's id.
     *
     * Falling back to the newest pending row for the same run and tool name
     * costs nothing in the common case and keeps nested calls landing on the
     * right card. Note this cannot be reproduced under fakes, which isolate
     * the property per invocation.
     *
     * @param \Crustum\Ai\Event\ToolInvoked $event Tool result event.
     * @return \Crustum\AiNexus\Model\Entity\AiNexusToolInvocation|null
     */
    protected function resolveRow(ToolInvoked $event): ?AiNexusToolInvocation
    {
        $pending = $this->toolInvocations->find()
            ->where(['invocation_id' => $event->invocationId, 'status' => 'pending']);

        $exact = (clone $pending)
            ->where(['tool_invocation_id' => $event->toolInvocationId])
            ->first();

        return $exact ?? (clone $pending)
            ->where(['name' => ToolNameResolver::resolve($event->tool)])
            ->orderBy(['started_at' => 'DESC'])
            ->first();
    }

    /**
     * Attach every row from this run to the assistant turn it produced.
     *
     * Rows that never get one — a run that failed before any message landed —
     * still render, ordered by `started_at`.
     *
     * @param string $invocationId Run invocation id.
     * @param string $messageId Assistant message id.
     * @return void
     */
    public function linkToMessage(string $invocationId, string $messageId): void
    {
        $this->toolInvocations->updateAll(
            ['message_id' => $messageId],
            ['invocation_id' => $invocationId, 'message_id IS' => null],
        );
    }

    /**
     * Whole milliseconds between two moments.
     *
     * @param \DateTimeInterface|null $start Earlier moment.
     * @param \DateTimeInterface $finished Later moment.
     * @return int
     */
    public static function durationMs(?DateTimeInterface $start, DateTimeInterface $finished): int
    {
        if (!$start instanceof DateTimeInterface) {
            return 0;
        }

        return (int)round(((float)$finished->format('U.u') - (float)$start->format('U.u')) * 1000);
    }
}
