<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Model\Entity;

use Cake\ORM\Entity;

/**
 * A single tool invocation row inside a dashboard conversation.
 *
 * @property string $id
 * @property string $conversation_id
 * @property string|null $message_id
 * @property string $invocation_id
 * @property string $tool_invocation_id
 * @property string $type
 * @property string $name
 * @property string $status
 * @property string|null $provider_status
 * @property string|null $error
 * @property array<string, mixed>|null $arguments
 * @property mixed $result
 * @property int|null $duration_ms
 * @property \Cake\I18n\DateTime|null $started_at
 * @property \Cake\I18n\DateTime|null $finished_at
 * @property \Cake\I18n\DateTime|null $created_at
 */
class AiNexusToolInvocation extends Entity
{
    /**
     * Mass-assignable fields, with the primary key guarded.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'id' => false,
        'conversation_id' => true,
        'message_id' => true,
        'invocation_id' => true,
        'tool_invocation_id' => true,
        'type' => true,
        'name' => true,
        'arguments' => true,
        'result' => true,
        'status' => true,
        'provider_status' => true,
        'error' => true,
        'duration_ms' => true,
        'started_at' => true,
        'finished_at' => true,
        'created_at' => true,
    ];
}
