<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Repository;

use Cake\Core\Configure;
use Closure;
use Crustum\AiNexus\Model\Table\AiNexusConversationsTable;
use Crustum\AiNexus\Model\Table\AiNexusMessagesTable;
use Crustum\AiNexus\Model\Table\AiNexusToolInvocationsTable;
use DateTimeInterface;

/**
 * Owns cascade deletion for dashboard conversations.
 *
 * Cascade happens here, not via database foreign keys, so it works on every
 * supported driver without pragma or migration differences.
 */
class ConversationRepository
{
    /**
     * Wire the repository with its tables and attachment file deleter.
     *
     * @param \Crustum\AiNexus\Model\Table\AiNexusConversationsTable $conversations Conversations table.
     * @param \Crustum\AiNexus\Model\Table\AiNexusMessagesTable $messages Messages table.
     * @param \Crustum\AiNexus\Model\Table\AiNexusToolInvocationsTable $toolInvocations Tool invocations table.
     * @param \Closure(string, string): void|null $deleteFile Deletes an attachment file by disk and path.
     */
    public function __construct(
        protected AiNexusConversationsTable $conversations,
        protected AiNexusMessagesTable $messages,
        protected AiNexusToolInvocationsTable $toolInvocations,
        protected ?Closure $deleteFile = null,
    ) {
    }

    /**
     * Delete conversations last updated before the given moment.
     *
     * @param \DateTimeInterface $before Cutoff moment.
     * @return int Number of conversations deleted.
     */
    public function prune(DateTimeInterface $before): int
    {
        $ids = [];

        $rows = $this->conversations->find()
            ->select(['id'])
            ->where(['updated_at <' => $before])
            ->all();

        foreach ($rows as $row) {
            $ids[] = (string)$row->get('id');
        }

        return $this->deleteConversations($ids);
    }

    /**
     * Delete every conversation.
     *
     * @return int Number of conversations deleted.
     */
    public function clear(): int
    {
        $ids = [];

        foreach ($this->conversations->find()->select(['id'])->all() as $row) {
            $ids[] = (string)$row->get('id');
        }

        return $this->deleteConversations($ids);
    }

    /**
     * Delete the given conversations along with their messages, tool
     * invocations, and stored attachment files.
     *
     * @param array<int, string> $ids Conversation ids.
     * @return int Number of conversations deleted.
     */
    public function deleteConversations(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $this->deleteAttachmentFiles($ids);

        $this->toolInvocations->deleteAll(['conversation_id IN' => $ids]);
        $this->messages->deleteAll(['conversation_id IN' => $ids]);

        return $this->conversations->deleteAll(['id IN' => $ids]);
    }

    /**
     * Remove uploaded attachment files belonging to the given conversations.
     *
     * @param array<int, string> $ids Conversation ids.
     * @return void
     */
    protected function deleteAttachmentFiles(array $ids): void
    {
        if (!$this->deleteFile instanceof Closure) {
            return;
        }

        $fallbackDisk = Configure::read('AiNexus.storage.attachments_disk', 'local');
        $fallbackDisk = is_string($fallbackDisk) ? $fallbackDisk : 'local';

        $rows = $this->messages->find()
            ->select(['attachments'])
            ->where(['conversation_id IN' => $ids, 'role' => 'user'])
            ->all();

        foreach ($rows as $row) {
            $attachments = $row->get('attachments');

            if (!is_array($attachments)) {
                continue;
            }

            foreach ($attachments as $attachment) {
                if (
                    !is_array($attachment)
                    || !isset($attachment['path'])
                    || !is_string($attachment['path'])
                    || $attachment['path'] === ''
                ) {
                    continue;
                }

                $disk = $attachment['filesystem'] ?? $attachment['disk'] ?? $fallbackDisk;
                $disk = is_string($disk) && $disk !== '' ? $disk : $fallbackDisk;

                ($this->deleteFile)($disk, $attachment['path']);
            }
        }
    }
}
