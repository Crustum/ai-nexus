<?php
declare(strict_types=1);

namespace Crustum\AiNexus;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;
use Crustum\AiNexus\Command\ClearCommand;
use Crustum\AiNexus\Command\PruneCommand;
use Crustum\AiNexus\Command\StatusCommand;
use Override;

/**
 * Plugin for Crustum/AiNexus
 */
class AiNexusPlugin extends BasePlugin
{
    /**
     * Load plugin configuration.
     *
     * @inheritDoc
     */
    #[Override]
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        if (!Configure::check('AiNexus')) {
            if (file_exists(CONFIG . 'ai_nexus.php')) {
                Configure::load('ai_nexus', 'default');
            } elseif (file_exists($this->getConfigPath() . 'ai_nexus.php')) {
                Configure::load('Crustum/AiNexus.ai_nexus', 'default', false);
            }
        }
    }

    /**
     * Register dashboard commands.
     *
     * @inheritDoc
     */
    #[Override]
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands = parent::console($commands);

        $commands->add(StatusCommand::defaultName(), StatusCommand::class);
        $commands->add(ClearCommand::defaultName(), ClearCommand::class);
        $commands->add(PruneCommand::defaultName(), PruneCommand::class);

        return $commands;
    }
}
