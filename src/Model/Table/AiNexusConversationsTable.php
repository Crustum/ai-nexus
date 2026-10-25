<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Model\Table;

use Cake\Core\Configure;
use Cake\ORM\Table;
use Crustum\AiNexus\Model\Entity\AiNexusConversation;
use Override;

/**
 * Dashboard conversations.
 *
 * @extends \Cake\ORM\Table<array{}, \Crustum\AiNexus\Model\Entity\AiNexusConversation>
 * @method \Crustum\AiNexus\Model\Entity\AiNexusConversation newEmptyEntity()
 * @method \Crustum\AiNexus\Model\Entity\AiNexusConversation newEntity(array<string, mixed> $data, array<string, mixed> $options = [])
 * @method \Crustum\AiNexus\Model\Entity\AiNexusConversation get(mixed $primaryKey, array<string, mixed> $options = [])
 * @method \Crustum\AiNexus\Model\Entity\AiNexusConversation saveOrFail(\Cake\Datasource\EntityInterface $entity, array<string, mixed> $options = [])
 */
class AiNexusConversationsTable extends Table
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

        $this->setTable('ainexus_conversations');
        $this->setEntityClass(AiNexusConversation::class);
        $this->setPrimaryKey('id');
        $this->setDisplayField('title');

        $this->addBehavior('Timestamp', [
            'events' => ['Model.beforeSave' => ['created_at' => 'new', 'updated_at' => 'always']],
        ]);

        $this->hasMany('AiNexusMessages', [
            'className' => AiNexusMessagesTable::class,
            'foreignKey' => 'conversation_id',
            'propertyName' => 'messages',
        ]);
        $this->hasMany('AiNexusToolInvocations', [
            'className' => AiNexusToolInvocationsTable::class,
            'foreignKey' => 'conversation_id',
            'propertyName' => 'tool_invocations',
        ]);
    }
}
