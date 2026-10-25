<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Chat;

use Cake\I18n\DateTime;
use Crustum\Ai\Streaming\Event\ProviderToolEvent;
use Crustum\Ai\Utility\Uuid;
use Crustum\AiNexus\Model\Entity\AiNexusToolInvocation;
use Crustum\AiNexus\Model\Table\AiNexusToolInvocationsTable;

/**
 * Records provider-native tools — web search, web fetch, file search, code
 * interpreter — which run inside the provider and never reach the SDK's tool
 * events.
 *
 * They exist only as `ProviderToolEvent`s in the stream, and the SDK passes the
 * provider's own vocabulary straight through without normalizing it:
 *
 * | Gateway       | `type`                                   | `status`                                    |
 * |---------------|------------------------------------------|---------------------------------------------|
 * | Anthropic     | `server_tool_use`, `*_tool_result`       | `started`, `result_received`, `completed`   |
 * | OpenAI / xAI  | `web_search_call`, `file_search_call`, … | `completed`, plus whatever the event name's |
 * |               |                                          | third segment says — an open set            |
 *
 * So AiNexus maps status into its own three and keeps the provider's word for
 * it in `provider_status`, because a debugging tool should never be the reason
 * you can't see what the provider actually said.
 */
class ProviderToolRecorder
{
    /**
     * Provider statuses that mean the tool finished successfully.
     *
     * @var array<int, string>
     */
    protected const array SUCCESS = ['completed', 'complete', 'result_received', 'done', 'succeeded'];

    /**
     * Provider statuses that mean it did not.
     *
     * @var array<int, string>
     */
    protected const array FAILED = ['failed', 'error', 'errored', 'incomplete', 'cancelled', 'canceled'];

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
     * Upsert the row for one provider-tool event.
     *
     * @param \Crustum\Ai\Streaming\Event\ProviderToolEvent $event Provider tool event.
     * @param string $invocationId Run invocation id.
     * @return void
     */
    public function record(ProviderToolEvent $event, string $invocationId): void
    {
        $row = $this->existingRow($event, $invocationId);
        $status = $this->normalizeStatus($event->status);
        $finishedAt = DateTime::now();

        $attributes = [
            'status' => $status,
            'provider_status' => $event->status,
        ];

        if ($status !== 'pending') {
            $attributes['result'] = $event->data;
            $attributes['finished_at'] = $finishedAt;
        }

        if ($status === 'error') {
            $attributes['error'] = $this->errorMessage($event);
        }

        if ($row instanceof AiNexusToolInvocation) {
            $attributes['duration_ms'] = $status === 'pending'
                ? $row->get('duration_ms')
                : AiNexusRecorder::durationMs($row->get('started_at'), $finishedAt);

            $row->patch($attributes);
            $this->toolInvocations->saveOrFail($row);

            return;
        }

        $created = $this->toolInvocations->newEntity([
            'conversation_id' => $this->conversationId,
            'invocation_id' => $invocationId,
            'tool_invocation_id' => $event->itemId,
            'type' => 'provider_tool',
            'name' => $this->name($event),
            'arguments' => $event->data,
            'started_at' => DateTime::now(),
            ...$attributes,
        ]);
        $created->set('id', Uuid::v7());

        $this->toolInvocations->saveOrFail($created);
    }

    /**
     * The tool's real name.
     *
     * `type` is not it: Anthropic reports the generic block type
     * `server_tool_use` and puts the name (`web_search`) in the payload. Falling
     * back to `type` covers OpenAI and xAI, whose item types (`web_search_call`)
     * are already specific.
     *
     * @param \Crustum\Ai\Streaming\Event\ProviderToolEvent $event Provider tool event.
     * @return string
     */
    protected function name(ProviderToolEvent $event): string
    {
        $name = $event->data['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : $event->type;
    }

    /**
     * Map the provider's status onto AiNexus's three.
     *
     * Unknown values deliberately fall through to `pending`: an in-flight card
     * that the catch-all can still resolve is a much better wrong answer than a
     * card confidently reporting a result that never arrived.
     *
     * @param string $status Provider status.
     * @return string
     */
    protected function normalizeStatus(string $status): string
    {
        $status = strtolower($status);

        return match (true) {
            in_array($status, self::SUCCESS, true) => 'success',
            in_array($status, self::FAILED, true) => 'error',
            default => 'pending',
        };
    }

    /**
     * The provider's failure reason for a failed tool event.
     *
     * @param \Crustum\Ai\Streaming\Event\ProviderToolEvent $event Provider tool event.
     * @return string
     */
    protected function errorMessage(ProviderToolEvent $event): string
    {
        foreach (['error', 'message', 'reason'] as $key) {
            $value = $event->data[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return 'The provider reported status "' . $event->status . '".';
    }

    /**
     * Find the row this event continues, if any.
     *
     * Correlation is best-effort by design. Anthropic keys the opening block on
     * `content_block.id` and the result block on `tool_use_id ?? id`, which only
     * match when the provider sends `tool_use_id`. When they don't match we
     * insert a second row: two truthful cards beat one card wearing another
     * call's result.
     *
     * @param \Crustum\Ai\Streaming\Event\ProviderToolEvent $event Provider tool event.
     * @param string $invocationId Run invocation id.
     * @return \Crustum\AiNexus\Model\Entity\AiNexusToolInvocation|null
     */
    protected function existingRow(ProviderToolEvent $event, string $invocationId): ?AiNexusToolInvocation
    {
        if ($event->itemId === '') {
            return null;
        }

        return $this->toolInvocations->find()
            ->where([
                'invocation_id' => $invocationId,
                'type' => 'provider_tool',
                'tool_invocation_id' => $event->itemId,
            ])
            ->first();
    }
}
