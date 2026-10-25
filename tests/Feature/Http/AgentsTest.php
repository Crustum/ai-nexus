<?php
declare(strict_types=1);

use Crustum\Ai\Ai;
use TestApp\Ai\Agents\ExtractorAgent;
use TestApp\Ai\Agents\SupportAgent;
use TestApp\Ai\Agents\WeatherAgent;

beforeEach(function (): void {
    Ai::manager()->resetFakeState();
    useTestAppAgents();
});

function agentRows(): array
{
    test()->get('/ai_nexus/api/agents');
    test()->assertResponseOk();

    return test()->viewVariable('agents');
}

it('lists discovered agents', function (): void {
    expect(array_column(agentRows(), 'class'))->toContain(SupportAgent::class);
});

it('serves the list as a bare array for the dashboard client', function (): void {
    test()->get('/ai_nexus/api/agents');
    test()->assertResponseOk();
    test()->assertResponseRegExp('/^\[/');
});

it('returns the documented payload shape', function (): void {
    $agent = null;

    foreach (agentRows() as $row) {
        if ($row['class'] === SupportAgent::class) {
            $agent = $row;
        }
    }

    expect($agent)->toHaveKeys([
        'slug', 'name', 'class', 'provider', 'model',
        'model_tier', 'tools', 'capabilities', 'available', 'error',
    ])
        ->and($agent['name'])->toBe('SupportAgent')
        ->and($agent['tools'][0])->toHaveKeys(['name', 'type'])
        ->and($agent['capabilities'])->toHaveKeys([
            'conversational', 'remembers_conversations', 'has_tools',
            'has_structured_output', 'has_middleware', 'can_act_as_tool',
        ]);
});

it('reports capabilities from the implemented interfaces', function (): void {
    $byClass = [];

    foreach (agentRows() as $row) {
        $byClass[$row['class']] = $row['capabilities'];
    }

    expect($byClass[SupportAgent::class])
        ->toMatchArray(['conversational' => true, 'has_tools' => true])
        ->and($byClass[WeatherAgent::class])
        ->toMatchArray(['conversational' => false, 'has_tools' => true])
        ->and($byClass[ExtractorAgent::class])
        ->toMatchArray(['has_structured_output' => true]);
});

it('forbids the endpoint when host middleware denies', function (): void {
    denyDashboard();

    test()->get('/ai_nexus/api/agents');
    test()->assertResponseCode(403);
});

it('returns the documented detail payload', function (): void {
    test()->get('/ai_nexus/api/agents/test-app.ai.agents.support-agent');
    test()->assertResponseOk();

    $payload = test()->viewVariable('data');

    expect($payload)->toHaveKeys([
        'slug', 'name', 'class', 'provider', 'model', 'model_tier', 'capabilities',
        'available', 'instructions', 'generation', 'provider_options',
        'middleware', 'tools', 'output_schema',
    ])
        ->and($payload['generation'])->toHaveKeys([
            'temperature', 'max_tokens', 'max_steps', 'top_p', 'timeout', 'strict', 'tool_choice',
        ]);
});

it('returns 404 for an unknown agent', function (): void {
    test()->get('/ai_nexus/api/agents/nope.not.here');
    test()->assertResponseCode(404);
});

it('forbids the detail endpoint when host middleware denies', function (): void {
    denyDashboard();

    test()->get('/ai_nexus/api/agents/test-app.ai.agents.support-agent');
    test()->assertResponseCode(403);
});

it('exposes the model options on the agent detail', function (): void {
    test()->get('/ai_nexus/api/agents/test-app.ai.agents.support-agent');
    test()->assertResponseOk();

    $payload = test()->viewVariable('data');

    expect($payload['models'][0]['id'])->toBe('gpt-5.6-luna')
        ->and($payload['models'][0]['tier'])->toBe('agent');
});
