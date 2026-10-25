<?php
declare(strict_types=1);

use Crustum\AiNexus\Discovery\AgentSlug;
use Crustum\AiNexus\Discovery\DiscoveredAgent;

it('serializes the dashboard payload with snake-case keys', function (): void {
    $agent = new DiscoveredAgent(
        slug: 'app.ai.agents.support-agent',
        name: 'SupportAgent',
        class: 'App\\Ai\\Agents\\SupportAgent',
        provider: 'openai',
        model: 'gpt-5.6-luna',
        modelTier: 'smartest',
        tools: [['name' => 'SearchProductsTool', 'type' => 'tool']],
        capabilities: ['streaming' => true],
    );

    expect($agent->toArray())->toBe([
        'slug' => 'app.ai.agents.support-agent',
        'name' => 'SupportAgent',
        'class' => 'App\\Ai\\Agents\\SupportAgent',
        'provider' => 'openai',
        'model' => 'gpt-5.6-luna',
        'model_tier' => 'smartest',
        'tools' => [['name' => 'SearchProductsTool', 'type' => 'tool']],
        'capabilities' => ['streaming' => true],
        'available' => true,
        'error' => null,
        'error_kind' => null,
        'unresolvable' => [],
    ]);
});

it('builds the unavailable payload from the class name', function (): void {
    $unresolvable = [
        ['parameter' => 'ledger', 'type' => 'LedgerClient', 'reason' => 'missing_class'],
    ];

    $agent = DiscoveredAgent::unavailable(
        'App\\Ai\\Agents\\BrokenAgent',
        'Could not wire the agent.',
        'binding',
        $unresolvable,
    );

    expect($agent->slug)->toBe(AgentSlug::make('App\\Ai\\Agents\\BrokenAgent'))
        ->and($agent->name)->toBe('BrokenAgent')
        ->and($agent->class)->toBe('App\\Ai\\Agents\\BrokenAgent')
        ->and($agent->available)->toBeFalse()
        ->and($agent->error)->toBe('Could not wire the agent.')
        ->and($agent->errorKind)->toBe('binding')
        ->and($agent->unresolvable)->toBe($unresolvable);
});
