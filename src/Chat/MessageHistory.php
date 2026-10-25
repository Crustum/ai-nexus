<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Chat;

use Crustum\Ai\Messages\AssistantMessage;
use Crustum\Ai\Messages\Message;
use Crustum\Ai\Messages\ToolResultMessage;
use Crustum\Ai\Messages\UserMessage;
use Crustum\Ai\Responses\Data\ToolCall;
use Crustum\Ai\Responses\Data\ToolResult;
use Crustum\AiNexus\Model\Entity\AiNexusMessage;
use Crustum\AiNexus\Model\Table\AiNexusMessagesTable;

/**
 * Rebuilds an SDK `Message[]` thread from AiNexus's own rows.
 *
 * This deliberately mirrors the SDK's own database conversation store step for
 * step. AiNexus stores replay data in the SDK's own serialization shapes
 * precisely so rehydration can follow the SDK's code rather than maintain a
 * parallel format — when the SDK's payloads evolve, ours evolve with them.
 */
class MessageHistory
{
    /**
     * Matches the SDK's own default for the newest messages of a conversation.
     */
    public const int LIMIT = 100;

    /**
     * Wire the history with the message rows it rehydrates.
     *
     * @param \Crustum\AiNexus\Model\Table\AiNexusMessagesTable $messages Messages table.
     * @param \Crustum\AiNexus\Chat\AttachmentStore $attachments Attachment rehydration.
     */
    public function __construct(
        protected AiNexusMessagesTable $messages,
        protected AttachmentStore $attachments,
    ) {
    }

    /**
     * The newest messages of the conversation, oldest first.
     *
     * @param string $conversationId Conversation id.
     * @param int $limit Newest rows to consider.
     * @return array<int, \Crustum\Ai\Messages\Message>
     */
    public function for(string $conversationId, int $limit = self::LIMIT): array
    {
        $rows = $this->messages->find()
            ->where(['conversation_id' => $conversationId])
            ->orderBy(['id' => 'DESC'])
            ->limit($limit)
            ->all()
            ->toList();

        $rows = array_reverse($rows);

        $history = [];

        foreach ($rows as $row) {
            foreach ($this->toMessages($row) as $message) {
                $history[] = $message;
            }
        }

        return $history;
    }

    /**
     * The SDK messages one stored row replays as.
     *
     * @param \Crustum\AiNexus\Model\Entity\AiNexusMessage $record Stored message row.
     * @return array<int, \Crustum\Ai\Messages\Message>
     */
    protected function toMessages(AiNexusMessage $record): array
    {
        if ($record->get('role') === 'error') {
            return [];
        }

        if ($record->get('role') === 'user') {
            $attachments = $record->get('attachments');
            $files = $this->attachments->rehydrate(is_array($attachments) ? $attachments : []);

            return $files === []
                ? [new Message('user', (string)$record->get('content'))]
                : [new UserMessage((string)$record->get('content'), $files)];
        }

        $storedCalls = $record->get('tool_calls');
        $storedResults = $record->get('tool_results');
        $toolCalls = collection(is_array($storedCalls) ? $storedCalls : []);
        $toolResults = collection(is_array($storedResults) ? $storedResults : []);

        if ($toolCalls->isEmpty()) {
            return [new AssistantMessage((string)$record->get('content'))];
        }

        $content = (string)$record->get('content');

        if ($toolResults->isEmpty()) {
            return $content !== '' ? [new AssistantMessage($content)] : [];
        }

        $messages = [
            new AssistantMessage('', $toolCalls->map(
                static fn(array $call): ToolCall => ToolCall::fromArray($call),
            )),
            new ToolResultMessage($toolResults->map(
                static fn(array $result): ToolResult => ToolResult::fromArray($result),
            )),
        ];

        if ($content !== '') {
            $messages[] = new AssistantMessage($content);
        }

        return $messages;
    }
}
