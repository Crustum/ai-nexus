<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Crustum\AiNexus\AiNexus;
use Override;

/**
 * Report dashboard configuration and every discovered agent.
 *
 * Debugging starts here, not in the UI: unavailable agents print their
 * failure reason, so a broken constructor or a misconfigured path shows up
 * as text instead of a silent empty page.
 *
 * Usage:
 * ```
 * bin/cake ai_nexus status
 * ```
 */
class StatusCommand extends AiNexusCommand
{
    /**
     * The command name.
     *
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'ai_nexus status';
    }

    /**
     * The command description.
     *
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Report AiNexus configuration and discovered agents';
    }

    /**
     * Print configuration, storage, and the agent table.
     *
     * @inheritDoc
     */
    #[Override]
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $io->out('AiNexus ' . AiNexus::version());
        $io->out('Enabled: ' . $this->yesNo((bool)Configure::read('AiNexus.enabled', false)));
        $io->out('Path: /' . trim((string)Configure::read('AiNexus.ui.path', 'ainexus'), '/'));
        $io->out('Storage: ' . $this->storage());
        $io->out('Retention: ' . $this->retention());

        $agents = $this->discovery()->all();

        $io->out('Agents discovered: ' . count($agents));

        $rows = [];

        foreach ($agents as $agent) {
            $rows[] = [
                $agent->slug,
                $agent->name,
                $agent->available ? 'yes' : 'no',
                $agent->available ? '' : (string)$agent->error,
            ];
        }

        $io->helper('Table')->output([
            ['Slug', 'Name', 'Available', 'Error'],
            ...$rows,
        ]);

        return static::CODE_SUCCESS;
    }

    /**
     * Storage connection and attachment disk in one line.
     *
     * @return string
     */
    protected function storage(): string
    {
        $connection = Configure::read('AiNexus.storage.connection');
        $disk = Configure::read('AiNexus.storage.attachments_disk', 'local');

        return (is_string($connection) && $connection !== '' ? $connection : 'default')
            . ' / ' . (is_string($disk) && $disk !== '' ? $disk : 'local');
    }

    /**
     * Retention window in one line.
     *
     * @return string
     */
    protected function retention(): string
    {
        if ((bool)Configure::read('AiNexus.retention.auto_prune', false)) {
            return sprintf('auto-prune after %s days', (int)Configure::read('AiNexus.retention.days', 7));
        }

        return 'kept until pruned manually';
    }

    /**
     * Flag word for console output.
     *
     * @param bool $value Flag value.
     * @return string
     */
    protected function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
