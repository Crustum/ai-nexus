<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Create the dashboard conversation messages table.
 */
class CreateAiNexusMessagesTable extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('ainexus_messages', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid', ['null' => false])
            ->addColumn('conversation_id', 'uuid', ['null' => false])
            ->addColumn('role', 'string', ['limit' => 25, 'null' => false])
            ->addColumn('content', 'text', ['null' => true, 'default' => null])
            ->addColumn('attachments', 'text', ['null' => false])
            ->addColumn('tool_calls', 'text', ['null' => false])
            ->addColumn('tool_results', 'text', ['null' => false])
            ->addColumn('usage', 'text', ['null' => false])
            ->addColumn('prompt_tokens', 'integer', ['null' => true, 'default' => null])
            ->addColumn('completion_tokens', 'integer', ['null' => true, 'default' => null])
            ->addColumn('duration_ms', 'integer', ['null' => true, 'default' => null])
            ->addColumn('meta', 'text', ['null' => false])
            ->addColumn('metadata', 'text', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['conversation_id'])
            ->addIndex(['conversation_id', 'id'])
            ->create();
    }
}
