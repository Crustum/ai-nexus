<?php
declare(strict_types=1);

namespace TestApp\Ai\Agents;

use Crustum\Ai\Contracts\Agent;
use Crustum\Ai\Trait\PromptableTrait;
use Stringable;

/**
 * Abstract agent fixture, never listed on the dashboard.
 */
abstract class BaseAgent implements Agent
{
    use PromptableTrait;

    /**
     * Describe the agent behavior.
     *
     * @return \Stringable|string
     */
    public function instructions(): Stringable|string
    {
        return 'Base instructions.';
    }
}
