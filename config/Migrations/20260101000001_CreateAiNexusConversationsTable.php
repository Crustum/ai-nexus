<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Create the dashboard conversations table.
 */
class CreateAiNexusConversationsTable extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('ainexus_conversations', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid', ['null' => false])
            ->addColumn('agent_class', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['agent_class'])
            ->addIndex(['updated_at'])
            ->create();
    }
}
