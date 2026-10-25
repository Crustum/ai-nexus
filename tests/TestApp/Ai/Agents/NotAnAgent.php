<?php
declare(strict_types=1);

namespace TestApp\Ai\Agents;

/**
 * Plain class living in the agents directory without implementing the contract.
 */
class NotAnAgent
{
    /**
     * Handle the call.
     *
     * @return string
     */
    public function handle(): string
    {
        return 'not an agent';
    }
}
