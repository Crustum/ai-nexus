<?php
declare(strict_types=1);

namespace TestApp\Ai\Agents;

use Crustum\Ai\Attributes\MaxSteps;
use Crustum\Ai\Attributes\MaxTokens;
use Crustum\Ai\Attributes\Model;
use Crustum\Ai\Attributes\Provider;
use Crustum\Ai\Attributes\Strict;
use Crustum\Ai\Attributes\Temperature;
use Crustum\Ai\Attributes\Timeout;
use Crustum\Ai\Attributes\TopP;
use Crustum\Ai\Contracts\Agent;
use Crustum\Ai\Contracts\HasMiddleware;
use Crustum\Ai\Contracts\HasProviderOptions;
use Crustum\Ai\Contracts\HasTools;
use Crustum\Ai\Enums\Lab;
use Crustum\Ai\Support\ToolChoice;
use Crustum\Ai\Trait\PromptableTrait;
use Stringable;
use TestApp\Ai\Middleware\PiiRedactor;
use TestApp\Ai\Tools\BrokenSchemaTool;
use TestApp\Ai\Tools\SearchProductsTool;

/**
 * Inspection fixture with every generation option set explicitly.
 */
#[Provider('openai')]
#[Model('gpt-5.6-luna')]
#[Temperature(0.7)]
#[MaxTokens(2048)]
#[MaxSteps(4)]
#[TopP(0.9)]
#[Timeout(45)]
#[Strict]
#[ToolChoice(ToolChoice::REQUIRED)]
class ConfiguredAgent implements Agent, HasMiddleware, HasProviderOptions, HasTools
{
    use PromptableTrait;

    /**
     * Describe the agent behavior.
     *
     * @return \Stringable|string
     */
    public function instructions(): Stringable|string
    {
        return "# Configured agent\n\nEvery generation option is set explicitly.";
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
            new BrokenSchemaTool(),
        ];
    }

    /**
     * List the middleware stack.
     *
     * @return array
     */
    public function middleware(): array
    {
        return [PiiRedactor::class];
    }

    /**
     * Declare provider-specific options.
     *
     * @param \Crustum\Ai\Enums\Lab|string $provider Provider name.
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return ['reasoning_effort' => 'high'];
    }
}
