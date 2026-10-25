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
use TestApp\Ai\Tools\SearchProductsTool;

/**
 * Conversational agent fixture with a user-defined tool.
 */
#[Provider('openai')]
#[Model('gpt-5.6-luna')]
class SupportAgent implements Agent, Conversational, HasTools
{
    use PromptableTrait;

    /**
     * Describe the agent behavior.
     *
     * @return \Stringable|string
     */
    public function instructions(): Stringable|string
    {
        return 'You are a friendly customer support agent. Use the product search tool to answer questions about the catalog.';
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
        ];
    }
}
