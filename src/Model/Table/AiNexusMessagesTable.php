<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Model\Table;

use Cake\Core\Configure;
use Cake\ORM\Table;
use Crustum\AiNexus\Model\Entity\AiNexusMessage;
use Override;

/**
 * Dashboard conversation messages.
 *
 * @extends \Cake\ORM\Table<array{}, \Crustum\AiNexus\Model\Entity\AiNexusMessage>
 * @method \Crustum\AiNexus\Model\Entity\AiNexusMessage newEmptyEntity()
 * @method \Crustum\AiNexus\Model\Entity\AiNexusMessage newEntity(array<string, mixed> $data, array<string, mixed> $options = [])
 * @method \Crustum\AiNexus\Model\Entity\AiNexusMessage get(mixed $primaryKey, array<string, mixed> $options = [])
 * @method \Crustum\AiNexus\Model\Entity\AiNexusMessage saveOrFail(\Cake\Datasource\EntityInterface $entity, array<string, mixed> $options = [])
 */
class AiNexusMessagesTable extends Table
{
    /**
     * The connection dashboard rows live on, from plugin config.
     *
     * @return string
     */
    #[Override]
    public static function defaultConnectionName(): string
    {
        $connection = Configure::read('AiNexus.storage.connection');

        return is_string($connection) && $connection !== '' ? $connection : 'default';
    }

    /**
     * Set the table, entity, timestamp behavior, JSON columns, and associations.
     *
     * @param array<string, mixed> $config Table configuration.
     * @return void
     */
    #[Override]
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('ainexus_messages');
        $this->setEntityClass(AiNexusMessage::class);
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => ['Model.beforeSave' => ['created_at' => 'new']],
        ]);

        $schema = $this->getSchema();
        $schema->setColumnType('attachments', 'json');
        $schema->setColumnType('tool_calls', 'json');
        $schema->setColumnType('tool_results', 'json');
        $schema->setColumnType('usage', 'json');
        $schema->setColumnType('meta', 'json');
        $schema->setColumnType('metadata', 'json');

        $this->belongsTo('AiNexusConversations', [
            'className' => AiNexusConversationsTable::class,
            'foreignKey' => 'conversation_id',
            'propertyName' => 'conversation',
        ]);
    }
}
