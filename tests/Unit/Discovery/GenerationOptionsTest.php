<?php
declare(strict_types=1);

use Crustum\AiNexus\Discovery\GenerationOptions;
use TestApp\Ai\Agents\ConfiguredAgent;
use TestApp\Ai\Agents\SupportAgent;

it('resolves generation options through the sdk resolver', function (): void {
    $generation = (new GenerationOptions())->for(new ConfiguredAgent());

    expect($generation['temperature'])->toBe(0.7)
        ->and($generation['max_tokens'])->toBe(2048)
        ->and($generation['max_steps'])->toBe(4)
        ->and($generation['top_p'])->toBe(0.9);
});

it('reads the timeout attribute and falls back to sixty', function (): void {
    expect((new GenerationOptions())->for(new ConfiguredAgent())['timeout'])->toBe(45)
        ->and((new GenerationOptions())->for(new SupportAgent())['timeout'])->toBe(60);
});

it('detects the strict attribute', function (): void {
    expect((new GenerationOptions())->for(new ConfiguredAgent())['strict'])->toBeTrue()
        ->and((new GenerationOptions())->for(new SupportAgent())['strict'])->toBeFalse();
});

it('reports the tool choice mode', function (): void {
    expect((new GenerationOptions())->for(new ConfiguredAgent())['tool_choice'])
        ->toBe(['mode' => 'required', 'tool' => null])
        ->and((new GenerationOptions())->for(new SupportAgent())['tool_choice'])->toBeNull();
});

it('returns provider options declared by the agent', function (): void {
    $generation = new GenerationOptions();

    expect($generation->providerOptions(new ConfiguredAgent(), 'openai'))
        ->toBe(['reasoning_effort' => 'high'])
        ->and($generation->providerOptions(new SupportAgent(), 'openai'))->toBe([])
        ->and($generation->providerOptions(new ConfiguredAgent(), null))->toBe([]);
});
