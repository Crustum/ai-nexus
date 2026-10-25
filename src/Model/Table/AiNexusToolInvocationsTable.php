<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Model\Table;

use Cake\Core\Configure;
use Cake\ORM\Table;
use Crustum\AiNexus\Model\Entity\AiNexusToolInvocation;
use Override;

/**
 * Dashboard conversation tool invocations.
 *
 * @extends \Cake\ORM\Table<array{}, \Crustum\AiNexus\Model\Entity\AiNexusToolInvocation>
 * @method \Crustum\AiNexus\Model\Entity\AiNexusToolInvocation newEmptyEntity()
 * @method \Crustum\AiNexus\Model\Entity\AiNexusToolInvocation newEntity(array<string, mixed> $data, array<string, mixed> $options = [])
 * @method \Crustum\AiNexus\Model\Entity\AiNexusToolInvocation get(mixed $primaryKey, array<string, mixed> $options = [])
 * @method \Crustum\AiNexus\Model\Entity\AiNexusToolInvocation saveOrFail(\Cake\Datasource\EntityInterface $entity, array<string, mixed> $options = [])
 */
class AiNexusToolInvocationsTable extends Table
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

        $this->setTable('ainexus_tool_invocations');
        $this->setEntityClass(AiNexusToolInvocation::class);
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => ['Model.beforeSave' => ['created_at' => 'new']],
        ]);

        $schema = $this->getSchema();
        $schema->setColumnType('arguments', 'json');
        $schema->setColumnType('result', 'json');

        $this->belongsTo('AiNexusConversations', [
            'className' => AiNexusConversationsTable::class,
            'foreignKey' => 'conversation_id',
            'propertyName' => 'conversation',
        ]);
        $this->belongsTo('AiNexusMessages', [
            'className' => AiNexusMessagesTable::class,
            'foreignKey' => 'message_id',
            'propertyName' => 'message',
        ]);
    }
}
