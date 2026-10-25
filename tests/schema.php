<?php
declare(strict_types=1);

/**
 * Test database schema for Crustum/AiNexus.
 *
 * @return array<int, array<string, mixed>>
 */
return [
    [
        'table' => 'ainexus_conversations',
        'columns' => [
            'id' => ['type' => 'uuid', 'null' => false],
            'agent_class' => ['type' => 'string', 'length' => 255, 'null' => false],
            'title' => ['type' => 'string', 'length' => 255, 'null' => false],
            'created_at' => ['type' => 'datetime', 'null' => true],
            'updated_at' => ['type' => 'datetime', 'null' => true],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ],
    [
        'table' => 'ainexus_messages',
        'columns' => [
            'id' => ['type' => 'uuid', 'null' => false],
            'conversation_id' => ['type' => 'uuid', 'null' => false],
            'role' => ['type' => 'string', 'length' => 25, 'null' => false],
            'content' => ['type' => 'text', 'null' => true],
            'attachments' => ['type' => 'text', 'null' => false],
            'tool_calls' => ['type' => 'text', 'null' => false],
            'tool_results' => ['type' => 'text', 'null' => false],
            'usage' => ['type' => 'text', 'null' => false],
            'prompt_tokens' => ['type' => 'integer', 'null' => true],
            'completion_tokens' => ['type' => 'integer', 'null' => true],
            'duration_ms' => ['type' => 'integer', 'null' => true],
            'meta' => ['type' => 'text', 'null' => false],
            'metadata' => ['type' => 'text', 'null' => false],
            'created_at' => ['type' => 'datetime', 'null' => true],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ],
    [
        'table' => 'ainexus_tool_invocations',
        'columns' => [
            'id' => ['type' => 'uuid', 'null' => false],
            'conversation_id' => ['type' => 'uuid', 'null' => false],
            'message_id' => ['type' => 'uuid', 'null' => true],
            'invocation_id' => ['type' => 'string', 'length' => 255, 'null' => false],
            'tool_invocation_id' => ['type' => 'string', 'length' => 255, 'null' => false],
            'type' => ['type' => 'string', 'length' => 25, 'null' => false],
            'name' => ['type' => 'string', 'length' => 255, 'null' => false],
            'arguments' => ['type' => 'text', 'null' => false],
            'result' => ['type' => 'text', 'null' => true],
            'status' => ['type' => 'string', 'length' => 25, 'null' => false],
            'provider_status' => ['type' => 'string', 'length' => 255, 'null' => true],
            'error' => ['type' => 'text', 'null' => true],
            'duration_ms' => ['type' => 'integer', 'null' => true],
            'started_at' => ['type' => 'datetime', 'null' => true],
            'finished_at' => ['type' => 'datetime', 'null' => true],
            'created_at' => ['type' => 'datetime', 'null' => true],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ],
];
