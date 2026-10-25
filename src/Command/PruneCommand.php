<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Override;

/**
 * Prune dashboard conversations older than the retention window.
 *
 * Usage:
 * ```
 * bin/cake ai_nexus prune
 * bin/cake ai_nexus prune --days 30
 * ```
 */
class PruneCommand extends AiNexusCommand
{
    /**
     * The command name.
     *
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'ai_nexus prune';
    }

    /**
     * The command description.
     *
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Prune AiNexus conversations older than the retention window';
    }

    /**
     * The command options.
     *
     * @inheritDoc
     */
    #[Override]
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->addOption('days', [
            'help' => 'The number of days of history to retain.',
            'default' => null,
        ]);

        return $parser;
    }

    /**
     * Delete conversations last updated before the retention cutoff.
     *
     * @inheritDoc
     */
    #[Override]
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $option = $args->getOption('days');
        $days = $option !== null && $option !== '' ? (int)$option : $this->retentionDays();

        $count = $this->repository()->prune(DateTime::now()->subDays($days));

        $io->out("Pruned {$count} conversation(s) older than {$days} day(s).");

        return static::CODE_SUCCESS;
    }

    /**
     * Configured retention window in days.
     *
     * @return int
     */
    protected function retentionDays(): int
    {
        $days = Configure::read('AiNexus.retention.days', 7);

        return is_int($days) ? $days : 7;
    }
}
