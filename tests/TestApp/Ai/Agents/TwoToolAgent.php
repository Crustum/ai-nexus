<?php
declare(strict_types=1);

namespace TestApp\Ai\Agents;

use Crustum\Ai\Attributes\Model;
use Crustum\Ai\Attributes\Provider;
use Crustum\Ai\Contracts\Agent;
use Crustum\Ai\Contracts\Conversational;
use Crustum\Ai\Contracts\HasTools;
use Crustum\Ai\Trait\PromptableTrait;
use Stringable;
use TestApp\Ai\Tools\GetWeatherTool;
use TestApp\Ai\Tools\SearchProductsTool;

/**
 * Conversational agent fixture with two tools.
 *
 * The SDK budgets generation steps from the declared tool count, so turns
 * that run more than one tool need an agent declaring more than one.
 */
#[Provider('openai')]
#[Model('gpt-5.6-luna')]
class TwoToolAgent implements Agent, Conversational, HasTools
{
    use PromptableTrait;

    /**
     * Describe the agent behavior.
     *
     * @return \Stringable|string
     */
    public function instructions(): Stringable|string
    {
        return 'You help with catalog questions and the weather.';
    }

    /**
     * List the conversation history seed messages.
     *
     * @return \Crustum\Ai\Messages\Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * List the exposed tools.
     *
     * @return iterable
     */
    public function tools(): iterable
    {
        return [
            new SearchProductsTool(),
            new GetWeatherTool(),
        ];
    }
}
