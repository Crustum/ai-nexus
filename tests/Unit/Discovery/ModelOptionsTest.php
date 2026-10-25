<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\AiNexus\Discovery\DiscoveredAgent;
use Crustum\AiNexus\Discovery\ModelOptions;

beforeEach(function (): void {
    Configure::delete('Ai.providers');
});

afterEach(function (): void {
    Configure::write('AiNexus.playground.models', []);
    Configure::load('Crustum/Ai.ai', 'default');
});

function testAgent(?string $model = null, string $modelTier = 'default'): DiscoveredAgent
{
    return new DiscoveredAgent(
        slug: 'test-agent',
        name: 'TestAgent',
        class: 'TestAgent',
        model: $model,
        modelTier: $modelTier,
    );
}

it('offers the agent model first as the default choice', function (): void {
    $options = (new ModelOptions())->for(testAgent('gpt-5.6-luna'));

    expect($options[0])->toBe(['id' => 'gpt-5.6-luna', 'label' => 'gpt-5.6-luna', 'tier' => 'agent']);
});

it('appends configured playground models after the agent model', function (): void {
    Configure::write('AiNexus.playground.models', ['openai/gpt-5', '', 42]);

    $options = (new ModelOptions())->for(testAgent('gpt-5.6-luna'));

    expect($options)->toBe([
        ['id' => 'gpt-5.6-luna', 'label' => 'gpt-5.6-luna', 'tier' => 'agent'],
        ['id' => 'openai/gpt-5', 'label' => 'openai/gpt-5', 'tier' => 'configured'],
    ]);
});

it('dedupes a configured model that matches the agent model', function (): void {
    Configure::write('AiNexus.playground.models', ['gpt-5.6-luna']);

    expect((new ModelOptions())->for(testAgent('gpt-5.6-luna')))->toBe([
        ['id' => 'gpt-5.6-luna', 'label' => 'gpt-5.6-luna', 'tier' => 'agent'],
    ]);
});

it('degrades to an empty list when no model resolves', function (): void {
    expect((new ModelOptions())->for(testAgent()))->toBe([]);
});

it('degrades to the agent model when the provider cannot be resolved', function (): void {
    $options = (new ModelOptions())->for(new DiscoveredAgent(
        slug: 'test-agent',
        name: 'TestAgent',
        class: 'TestAgent',
        provider: 'no-such-provider',
        model: 'gpt-5.6-luna',
    ));

    expect($options)->toBe([
        ['id' => 'gpt-5.6-luna', 'label' => 'gpt-5.6-luna', 'tier' => 'agent'],
    ]);
});
