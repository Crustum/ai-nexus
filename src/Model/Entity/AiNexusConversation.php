<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Model\Entity;

use Cake\ORM\Entity;

/**
 * A single dashboard conversation thread.
 *
 * @property string $id
 * @property string $agent_class
 * @property string $title
 * @property \Cake\I18n\DateTime|null $created_at
 * @property \Cake\I18n\DateTime|null $updated_at
 */
class AiNexusConversation extends Entity
{
    /**
     * Mass-assignable fields, with the primary key guarded.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'id' => false,
        'agent_class' => true,
        'title' => true,
        'created_at' => true,
        'updated_at' => true,
    ];
}
