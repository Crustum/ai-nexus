<?php
declare(strict_types=1);

namespace TestApp\Ai\Agents;

use Crustum\Ai\Attributes\Model;
use Crustum\Ai\Attributes\Provider;
use Crustum\Ai\Contracts\Agent;
use Crustum\Ai\Trait\PromptableTrait;
use Stringable;

/**
 * Valid agent fixture used to exercise the discovery ignore list.
 */
#[Provider('openai')]
#[Model('gpt-5.6-luna')]
class HiddenAgent implements Agent
{
    use PromptableTrait;

    /**
     * Describe the agent behavior.
     *
     * @return \Stringable|string
     */
    public function instructions(): Stringable|string
    {
        return 'You should not be listed when ignored.';
    }
}
