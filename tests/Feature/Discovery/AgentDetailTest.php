<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\AiNexus\Discovery\AgentDetail;
use Crustum\AiNexus\Discovery\AgentDiscovery;
use Crustum\AiNexus\Discovery\AgentMetadata;
use Crustum\AiNexus\Discovery\GenerationOptions;
use Crustum\AiNexus\Discovery\InstantiationDiagnostic;
use Crustum\AiNexus\Discovery\ModelOptions;
use Crustum\AiNexus\Discovery\ToolClassifier;
use Crustum\AiNexus\Discovery\ToolDetail;
use League\Container\Container;
use League\Container\ReflectionContainer;
use TestApp\Ai\Agents\BrokenAgent;
use TestApp\Ai\Agents\ConfiguredAgent;
use TestApp\Ai\Agents\ExtractorAgent;
use TestApp\Ai\Agents\KitchenSinkAgent;
use TestApp\Ai\Agents\ResearchAgent;
use TestApp\Ai\Agents\SupportAgent;
use TestApp\Ai\Agents\WeatherAgent;
use TestApp\Ai\Middleware\PiiRedactor;
use function Cake\Collection\collection;

function detailFor(string $class): array
{
    Configure::write('AiNexus.discovery.paths', [dirname(__DIR__, 2) . '/TestApp/Ai/Agents']);
    Configure::write('AiNexus.discovery.ignore', []);

    $container = new Container();
    $container->delegate(new ReflectionContainer());

    $discovery = new AgentDiscovery(
        new AgentMetadata(new ToolClassifier(), new InstantiationDiagnostic($container), $container),
        $container,
    );
    $discovered = null;

    foreach ($discovery->all() as $agent) {
        if ($agent->class === $class) {
            $discovered = $agent;
        }
    }

    $detail = new AgentDetail(new GenerationOptions(), new ToolDetail(), new ModelOptions(), $container);

    return $detail->for($discovered);
}

afterEach(function (): void {
    Configure::write('AiNexus.discovery.paths', []);
    Configure::write('AiNexus.discovery.ignore', []);
});

it('resolves generation options through the sdk resolver', function (): void {
    $generation = detailFor(ConfiguredAgent::class)['generation'];

    expect($generation['temperature'])->toBe(0.7)
        ->and($generation['max_tokens'])->toBe(2048)
        ->and($generation['max_steps'])->toBe(4)
        ->and($generation['top_p'])->toBe(0.9);
});

it('reads the timeout attribute and falls back to sixty', function (): void {
    expect(detailFor(ConfiguredAgent::class)['generation']['timeout'])->toBe(45)
        ->and(detailFor(SupportAgent::class)['generation']['timeout'])->toBe(60);
});

it('detects the strict attribute', function (): void {
    expect(detailFor(ConfiguredAgent::class)['generation']['strict'])->toBeTrue()
        ->and(detailFor(SupportAgent::class)['generation']['strict'])->toBeFalse();
});

it('reports the tool choice mode', function (): void {
    expect(detailFor(ConfiguredAgent::class)['generation']['tool_choice'])
        ->toBe(['mode' => 'required', 'tool' => null])
        ->and(detailFor(SupportAgent::class)['generation']['tool_choice'])->toBeNull();
});

it('returns the agent instructions', function (): void {
    expect(detailFor(SupportAgent::class)['instructions'])->toContain('friendly customer support agent');
});

it('lists middleware class names', function (): void {
    expect(detailFor(ConfiguredAgent::class)['middleware'])
        ->toBe([PiiRedactor::class])
        ->and(detailFor(SupportAgent::class)['middleware'])->toBe([]);
});

it('returns provider options declared by the agent', function (): void {
    expect(detailFor(ConfiguredAgent::class)['provider_options'])->toBe(['reasoning_effort' => 'high']);
});

it('describes a user tool with its parameters and required flags', function (): void {
    $tool = collection(detailFor(SupportAgent::class)['tools'])->firstMatch(['name' => 'SearchProductsTool']);

    expect($tool['type'])->toBe('tool')
        ->and($tool['description'])->toContain('Search the product catalog');

    $parameters = collection($tool['parameters']);

    expect($parameters->firstMatch(['name' => 'query']))->toBe([
        'name' => 'query',
        'type' => 'string',
        'description' => 'Search query text',
        'required' => true,
    ])->and($parameters->firstMatch(['name' => 'max_results'])['required'])->toBeFalse();
});

it('describes a provider tool without calling description or schema', function (): void {
    $tool = collection(detailFor(ResearchAgent::class)['tools'])->firstMatch(['type' => 'provider_tool']);

    expect($tool['name'])->toBe('WebSearch')
        ->and($tool['description'])->toBeNull()
        ->and($tool['parameters'])->toBe([])
        ->and($tool['provider_options'])->toBeArray();
});

it('links a sub-agent tool to its own slug', function (): void {
    Configure::write('AiNexus.discovery.paths', [dirname(__DIR__, 2) . '/TestApp/Ai/Agents']);
    Configure::write('AiNexus.discovery.ignore', []);

    $container = new Container();
    $container->delegate(new ReflectionContainer());

    $discovery = new AgentDiscovery(
        new AgentMetadata(new ToolClassifier(), new InstantiationDiagnostic($container), $container),
        $container,
    );

    $tool = collection(detailFor(KitchenSinkAgent::class)['tools'])->firstMatch(['type' => 'agent']);

    expect($tool['name'])->toBe('WeatherAgent')
        ->and($tool['agent_slug'])->toBe('test-app.ai.agents.weather-agent')
        ->and($discovery->find($tool['agent_slug'])->class)->toBe(WeatherAgent::class);
});

it('degrades a tool whose schema throws without breaking the payload', function (): void {
    $detail = detailFor(ConfiguredAgent::class);
    $broken = collection($detail['tools'])->firstMatch(['name' => 'BrokenSchemaTool']);

    expect($broken['schema_error'])->toContain('Schema could not be built')
        ->and(collection($detail['tools'])->firstMatch(['name' => 'SearchProductsTool'])['parameters'])->not->toBeEmpty()
        ->and($detail['instructions'])->not->toBeNull();
});

it('returns the output schema only for structured-output agents', function (): void {
    $schema = detailFor(ExtractorAgent::class)['output_schema'];

    expect(collection($schema)->firstMatch(['name' => 'name']))
        ->toMatchArray(['type' => 'string', 'required' => true])
        ->and(detailFor(SupportAgent::class)['output_schema'])->toBeNull();
});

it('returns an empty detail for an unavailable agent', function (): void {
    $detail = detailFor(BrokenAgent::class);

    expect($detail['available'])->toBeFalse()
        ->and($detail['error_kind'])->toBe('binding')
        ->and($detail['generation'])->toBeNull()
        ->and($detail['tools'])->toBe([]);
});
