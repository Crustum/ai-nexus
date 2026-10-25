<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Discovery;

use Crustum\Ai\Attributes\Strict;
use Crustum\Ai\Attributes\Timeout as TimeoutAttribute;
use Crustum\Ai\Contracts\Agent;
use Crustum\Ai\Contracts\HasProviderOptions;
use Crustum\Ai\Gateway\TextGenerationOptions;
use Crustum\Ai\Support\ToolChoice;
use ReflectionClass;
use ReflectionMethod;

/**
 * The generation settings the SDK will actually use for an agent.
 *
 * Everything except the timeout comes from TextGenerationOptions::forAgent,
 * the SDK's own resolver. The timeout precedence (method, then attribute,
 * then 60) mirrors the promptable trait's protected resolver.
 */
class GenerationOptions
{
    /**
     * Generation settings payload for the given agent.
     *
     * @param object $agent Agent under inspection.
     * @return array<string, mixed>
     */
    public function for(object $agent): array
    {
        assert($agent instanceof Agent);

        $options = TextGenerationOptions::forAgent($agent);

        return [
            'temperature' => $options->temperature,
            'max_tokens' => $options->maxTokens,
            'max_steps' => $options->maxSteps,
            'top_p' => $options->topP,
            'timeout' => $this->timeout($agent),
            'strict' => Strict::isAppliedTo($agent),
            'tool_choice' => $this->toolChoice($options->toolChoice),
        ];
    }

    /**
     * Provider-specific options the agent declares, if any.
     *
     * @param object $agent Agent under inspection.
     * @param string|null $provider Provider name.
     * @return array<string, mixed>
     */
    public function providerOptions(object $agent, ?string $provider): array
    {
        if (!$agent instanceof HasProviderOptions || $provider === null) {
            return [];
        }

        return $agent->providerOptions($provider);
    }

    /**
     * Resolve the timeout: public method, then attribute, then 60.
     *
     * Protected declarations are skipped: they are SDK declaration helpers,
     * not callable metadata endpoints.
     *
     * @param object $agent Agent under inspection.
     * @return int
     */
    protected function timeout(object $agent): int
    {
        $method = method_exists($agent, 'timeout') ? new ReflectionMethod($agent, 'timeout') : null;

        if ($method instanceof ReflectionMethod && $method->isPublic()) {
            return (int)$agent->timeout();
        }

        $attributes = (new ReflectionClass($agent))->getAttributes(TimeoutAttribute::class);

        return $attributes === [] ? 60 : (int)$attributes[0]->newInstance()->value;
    }

    /**
     * Serialize the tool choice, if the agent declares one.
     *
     * @param \Crustum\Ai\Support\ToolChoice|null $choice Tool choice value.
     * @return array{mode: string, tool: string|null}|null
     */
    protected function toolChoice(?ToolChoice $choice): ?array
    {
        if (!$choice instanceof ToolChoice) {
            return null;
        }

        return ['mode' => $choice->mode, 'tool' => $choice->toolName];
    }
}
