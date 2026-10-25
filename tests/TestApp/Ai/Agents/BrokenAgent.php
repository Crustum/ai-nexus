<?php
declare(strict_types=1);

namespace TestApp\Ai\Agents;

use Crustum\Ai\Contracts\Agent;
use Crustum\Ai\Trait\PromptableTrait;
use Stringable;
use TestApp\Ai\Contracts\UnresolvableDependency;

/**
 * Agent fixture declaring a dependency the container cannot build.
 */
class BrokenAgent implements Agent
{
    use PromptableTrait;

    /**
     * @param \TestApp\Ai\Contracts\UnresolvableDependency $dependency Unresolvable dependency.
     */
    public function __construct(protected UnresolvableDependency $dependency)
    {
    }

    /**
     * Describe the agent behavior.
     *
     * @return \Stringable|string
     */
    public function instructions(): Stringable|string
    {
        return 'Never reachable.';
    }
}
