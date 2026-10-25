<?php
declare(strict_types=1);

use Crustum\Ai\Attributes\Strict;
use Crustum\Ai\Contracts\Conversational;
use Crustum\Ai\Contracts\HasMiddleware;
use Crustum\Ai\Contracts\HasProviderOptions;
use Crustum\Ai\Contracts\HasStructuredOutput;
use Crustum\Ai\Contracts\HasTools;
use Crustum\Ai\Gateway\TextGenerationOptions;
use Crustum\Ai\Messages\Message;
use Crustum\Ai\Trait\RemembersConversationsTrait;
use Crustum\AiNexus\Chat\AiNexusConversationalAgent;
use Crustum\AiNexus\Chat\StrictAiNexusConversationalAgent;
use TestApp\Ai\Agents\ConfiguredAgent;
use TestApp\Ai\Agents\StrictAgent;
use TestApp\Ai\Agents\SupportAgent;
use TestApp\Ai\Agents\WeatherAgent;

function generationOptionsFor(object $agent): array
{
    $options = TextGenerationOptions::forAgent($agent);

    return [
        'maxSteps' => $options->maxSteps,
        'maxTokens' => $options->maxTokens,
        'temperature' => $options->temperature,
        'topP' => $options->topP,
        'toolChoice' => $options->toolChoice?->mode,
        'toolChoiceName' => $options->toolChoice?->toolName,
    ];
}

function resolveOnAgent(object $agent, string $method, mixed ...$arguments): mixed
{
    return (new ReflectionMethod($agent, $method))->invoke($agent, ...$arguments);
}

function classUsesRecursive(object $object): array
{
    $traits = [];
    $class = new ReflectionClass($object);

    while ($class instanceof ReflectionClass) {
        foreach ($class->getTraitNames() as $trait) {
            $traits[] = $trait;

            foreach ((new ReflectionClass($trait))->getTraitNames() as $nested) {
                $traits[] = $nested;
            }
        }

        $class = $class->getParentClass();
    }

    return array_unique($traits);
}

it('resolves every generation option exactly as the unwrapped agent does', function (): void {
    $agent = new ConfiguredAgent();

    expect(generationOptionsFor(AiNexusConversationalAgent::for($agent)))
        ->toBe(generationOptionsFor($agent));
});

it('keeps the provider and model the agent declares', function (): void {
    $agent = new SupportAgent();

    expect(resolveOnAgent(AiNexusConversationalAgent::for($agent), 'getProvidersAndModels', null, null))
        ->toBe(resolveOnAgent($agent, 'getProvidersAndModels', null, null))
        ->toBe(['openai' => 'gpt-5.6-luna']);
});

it('keeps the agent timeout rather than falling back to the SDK default', function (): void {
    $agent = new ConfiguredAgent();

    $wrapped = resolveOnAgent(AiNexusConversationalAgent::for($agent), 'getTimeout', null);

    expect($wrapped)->toBe(resolveOnAgent($agent, 'getTimeout', null))
        ->not->toBe(60);
});

it('preserves the strict attribute across the wrap', function (): void {
    $agent = new StrictAgent();

    expect(Strict::isAppliedTo($agent))->toBeTrue()
        ->and(Strict::isAppliedTo(AiNexusConversationalAgent::for($agent)))->toBeTrue();
});

it('uses the strict variant only for strict agents', function (): void {
    expect(AiNexusConversationalAgent::for(new StrictAgent()))
        ->toBeInstanceOf(StrictAiNexusConversationalAgent::class);

    expect(AiNexusConversationalAgent::for(new SupportAgent()))
        ->not->toBeInstanceOf(StrictAiNexusConversationalAgent::class);
});

it('forwards instructions, tools, middleware and provider options', function (): void {
    $agent = new ConfiguredAgent();
    $wrapper = AiNexusConversationalAgent::for($agent);

    expect((string)$wrapper->instructions())->toBe((string)$agent->instructions())
        ->and(count([...$wrapper->tools()]))->toBe(count([...$agent->tools()]))
        ->and($wrapper->middleware())->toBe($agent->middleware())
        ->and($wrapper->providerOptions('openai'))->toBe($agent->providerOptions('openai'));
});

it('returns empty capabilities for an agent that has none', function (): void {
    $wrapper = AiNexusConversationalAgent::for(new WeatherAgent());

    expect($wrapper->middleware())->toBe([])
        ->and($wrapper->providerOptions('openai'))->toBe([]);
});

it('supplies AiNexus history instead of the agent own messages', function (): void {
    $history = [new Message('user', 'Earlier question')];

    $wrapper = AiNexusConversationalAgent::for(new SupportAgent(), $history);

    expect([...$wrapper->messages()])->toBe($history)
        ->and([...(new SupportAgent())->messages()])->toBe([]);
});

it('carries the contracts the SDK branches on', function (): void {
    $wrapper = AiNexusConversationalAgent::for(new SupportAgent());

    expect($wrapper)->toBeInstanceOf(Conversational::class)
        ->toBeInstanceOf(HasTools::class)
        ->toBeInstanceOf(HasMiddleware::class)
        ->toBeInstanceOf(HasProviderOptions::class);
});

it('never claims structured output', function (): void {
    expect(AiNexusConversationalAgent::for(new SupportAgent()))
        ->not->toBeInstanceOf(HasStructuredOutput::class);
});

it('does not pull in the SDK conversation middleware', function (): void {
    expect(classUsesRecursive(AiNexusConversationalAgent::for(new SupportAgent())))
        ->not->toContain(RemembersConversationsTrait::class);
});

it('exposes the agent it wraps', function (): void {
    $agent = new SupportAgent();

    expect(AiNexusConversationalAgent::for($agent)->wrapped())->toBe($agent);
});
