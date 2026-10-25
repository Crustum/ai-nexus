<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Override;

/**
 * Clear all dashboard conversation history, including stored attachments.
 *
 * Usage:
 * ```
 * bin/cake ai_nexus clear
 * ```
 */
class ClearCommand extends AiNexusCommand
{
    /**
     * The command name.
     *
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'ai_nexus clear';
    }

    /**
     * The command description.
     *
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Clear all AiNexus conversation history, including stored attachments';
    }

    /**
     * Delete every conversation with its messages, tool rows, and files.
     *
     * @inheritDoc
     */
    #[Override]
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $count = $this->repository()->clear();

        $io->out("Cleared {$count} AiNexus conversation(s).");

        return static::CODE_SUCCESS;
    }
}
