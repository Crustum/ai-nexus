<?php
declare(strict_types=1);

namespace TestApp\Ai\Agents;

use Crustum\Ai\Attributes\Model;
use Crustum\Ai\Attributes\Provider;
use Crustum\Ai\Contracts\Agent;
use Crustum\Ai\Contracts\Conversational;
use Crustum\Ai\Contracts\HasTools;
use Crustum\Ai\Providers\Tools\WebSearch;
use Crustum\Ai\Trait\PromptableTrait;
use Stringable;

/**
 * Conversational agent fixture declaring a provider-native tool.
 */
#[Provider('openai')]
#[Model('gpt-5.6-luna')]
class ResearchAgent implements Agent, Conversational, HasTools
{
    use PromptableTrait;

    /**
     * Describe the agent behavior.
     *
     * @return \Stringable|string
     */
    public function instructions(): Stringable|string
    {
        return 'You are a research assistant. Use web search to find up-to-date information.';
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
            new WebSearch(maxSearches: 5),
        ];
    }
}
