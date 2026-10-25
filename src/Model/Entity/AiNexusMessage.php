<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Model\Entity;

use Cake\ORM\Entity;

/**
 * A single message row inside a dashboard conversation.
 *
 * @property string $id
 * @property string $conversation_id
 * @property string $role
 * @property string|null $content
 * @property array<int, mixed> $attachments
 * @property array<int, mixed> $tool_calls
 * @property array<int, mixed> $tool_results
 * @property array<string, mixed> $usage
 * @property array<string, mixed> $meta
 * @property array<string, mixed> $metadata
 * @property int|null $prompt_tokens
 * @property int|null $completion_tokens
 * @property int|null $duration_ms
 * @property \Cake\I18n\DateTime|null $created_at
 */
class AiNexusMessage extends Entity
{
    /**
     * Mass-assignable fields, with the primary key guarded.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'id' => false,
        'conversation_id' => true,
        'role' => true,
        'content' => true,
        'attachments' => true,
        'tool_calls' => true,
        'tool_results' => true,
        'usage' => true,
        'meta' => true,
        'metadata' => true,
        'prompt_tokens' => true,
        'completion_tokens' => true,
        'duration_ms' => true,
        'created_at' => true,
    ];
}
