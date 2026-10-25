<?php
declare(strict_types=1);

namespace TestApp\Ai\Agents;

use Crustum\Ai\Attributes\UseSmartestModel;
use Crustum\Ai\Contracts\Agent;
use Crustum\Ai\Contracts\Conversational;
use Crustum\Ai\Contracts\HasTools;
use Crustum\Ai\Providers\Tools\WebSearch;
use Crustum\Ai\Trait\PromptableTrait;
use Stringable;
use TestApp\Ai\Tools\GetWeatherTool;
use TestApp\Ai\Tools\SearchProductsTool;

/**
 * Mixed-kind tool agent fixture with no explicit model.
 */
#[UseSmartestModel]
class KitchenSinkAgent implements Agent, Conversational, HasTools
{
    use PromptableTrait;

    /**
     * Describe the agent behavior.
     *
     * @return \Stringable|string
     */
    public function instructions(): Stringable|string
    {
        return 'You have a lot of tools at your disposal.';
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
            new WebSearch(),
            new WeatherAgent(),
            new SearchProductsTool(),
            new GetWeatherTool(),
            new SearchProductsTool(),
        ];
    }
}
