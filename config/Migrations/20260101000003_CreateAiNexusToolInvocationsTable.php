<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Create the dashboard conversation tool invocations table.
 */
class CreateAiNexusToolInvocationsTable extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('ainexus_tool_invocations', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid', ['null' => false])
            ->addColumn('conversation_id', 'uuid', ['null' => false])
            ->addColumn('message_id', 'uuid', ['null' => true, 'default' => null])
            ->addColumn('invocation_id', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('tool_invocation_id', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('type', 'string', ['limit' => 25, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('arguments', 'text', ['null' => false])
            ->addColumn('result', 'text', ['null' => true, 'default' => null])
            ->addColumn('status', 'string', ['limit' => 25, 'null' => false])
            ->addColumn('provider_status', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('error', 'text', ['null' => true, 'default' => null])
            ->addColumn('duration_ms', 'integer', ['null' => true, 'default' => null])
            ->addColumn('started_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('finished_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['conversation_id'])
            ->addIndex(['tool_invocation_id'])
            ->create();
    }
}
