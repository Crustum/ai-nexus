<?php
declare(strict_types=1);

namespace TestApp\Ai\Agents;

use Crustum\Ai\Attributes\Model;
use Crustum\Ai\Attributes\Provider;
use Crustum\Ai\Contracts\Agent;
use Crustum\Ai\Contracts\HasTools;
use Crustum\Ai\Trait\PromptableTrait;
use Stringable;
use TestApp\Ai\Tools\GetWeatherTool;

/**
 * Stateless agent fixture with a tool.
 */
#[Provider('openai')]
#[Model('gpt-5.6-luna')]
class WeatherAgent implements Agent, HasTools
{
    use PromptableTrait;

    /**
     * Describe the agent behavior.
     *
     * @return \Stringable|string
     */
    public function instructions(): Stringable|string
    {
        return 'You are a weather assistant. Use the weather tool to answer questions.';
    }

    /**
     * List the exposed tools.
     *
     * @return iterable
     */
    public function tools(): iterable
    {
        return [
            new GetWeatherTool(),
        ];
    }
}
