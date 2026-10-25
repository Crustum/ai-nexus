<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Test\Support;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * In-memory PSR logger counting logged database queries.
 */
class QueryCounter extends AbstractLogger
{
    /**
     * Logged query count.
     *
     * @var int
     */
    public int $count = 0;

    /**
     * Count one logged query.
     *
     * @param mixed $level Log level.
     * @param string|\Stringable $message Log message.
     * @param array $context Log context.
     * @return void
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->count++;
    }
}
