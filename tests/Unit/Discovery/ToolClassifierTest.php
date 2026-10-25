<?php
declare(strict_types=1);

use Crustum\AiNexus\Discovery\ToolClassifier;
use TestApp\Ai\Agents\ExtractorAgent;
use TestApp\Ai\Agents\KitchenSinkAgent;
use TestApp\Ai\Agents\ResearchAgent;
use TestApp\Ai\Agents\SupportAgent;
use function Cake\Collection\collection;

it('classifies a user-defined tool', function (): void {
    $tools = (new ToolClassifier())->classify(new SupportAgent());

    expect($tools)->toHaveCount(1)
        ->and($tools[0])->toBe(['name' => 'SearchProductsTool', 'type' => 'tool']);
});

it('classifies a provider tool without touching description or schema', function (): void {
    $tools = (new ToolClassifier())->classify(new ResearchAgent());

    expect($tools)->toBe([['name' => 'WebSearch', 'type' => 'provider_tool']]);
});

it('classifies a sub-agent used as a tool', function (): void {
    $tools = collection((new ToolClassifier())->classify(new KitchenSinkAgent()));

    expect($tools->firstMatch(['type' => 'agent']))->toBe(['name' => 'WeatherAgent', 'type' => 'agent']);
});

it('returns an empty list for agents without tools', function (): void {
    expect((new ToolClassifier())->classify(new ExtractorAgent()))->toBe([]);
});

it('classifies every entry of a mixed tool list', function (): void {
    $tools = (new ToolClassifier())->classify(new KitchenSinkAgent());

    $types = collection($tools)->map(fn(array $tool): string => $tool['type'])->toList();
    sort($types);

    expect($tools)->toHaveCount(7)
        ->and(array_values(array_unique($types)))->toBe(['agent', 'provider_tool', 'tool']);
});
