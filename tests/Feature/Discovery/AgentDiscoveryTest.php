<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\AiNexus\Discovery\AgentDiscovery;
use Crustum\AiNexus\Discovery\AgentMetadata;
use Crustum\AiNexus\Discovery\DiscoveredAgent;
use Crustum\AiNexus\Discovery\InstantiationDiagnostic;
use Crustum\AiNexus\Discovery\ToolClassifier;
use League\Container\Container;
use League\Container\ReflectionContainer;
use TestApp\Ai\Agents\BaseAgent;
use TestApp\Ai\Agents\BrokenAgent;
use TestApp\Ai\Agents\HiddenAgent;
use TestApp\Ai\Agents\KitchenSinkAgent;
use TestApp\Ai\Agents\NotAnAgent;
use TestApp\Ai\Agents\SupportAgent;
use TestApp\Ai\Agents\WeatherAgent;

function testContainer(): Container
{
    $container = new Container();
    $container->delegate(new ReflectionContainer());

    return $container;
}

function testDiscovery(array $config = []): AgentDiscovery
{
    Configure::write('AiNexus.discovery.paths', $config['paths'] ?? [dirname(__DIR__, 2) . '/TestApp/Ai/Agents']);
    Configure::write('AiNexus.discovery.ignore', $config['ignore'] ?? []);

    $container = testContainer();

    return new AgentDiscovery(
        new AgentMetadata(new ToolClassifier(), new InstantiationDiagnostic($container), $container),
        $container,
    );
}

function discoveredClasses(AgentDiscovery $discovery): array
{
    return array_map(static fn(DiscoveredAgent $agent): string => $agent->class, $discovery->all());
}

function firstAgent(AgentDiscovery $discovery, string $class): ?DiscoveredAgent
{
    foreach ($discovery->all() as $agent) {
        if ($agent->class === $class) {
            return $agent;
        }
    }

    return null;
}

afterEach(function (): void {
    Configure::write('AiNexus.discovery.paths', []);
    Configure::write('AiNexus.discovery.ignore', []);
    Configure::delete('Ai.default');
});

it('finds agent classes in the configured paths', function (): void {
    expect(discoveredClasses(testDiscovery()))
        ->toContain(SupportAgent::class)
        ->toContain(WeatherAgent::class);
});

it('ignores classes that do not implement the agent contract', function (): void {
    expect(discoveredClasses(testDiscovery()))->not->toContain(NotAnAgent::class);
});

it('ignores abstract agents', function (): void {
    expect(discoveredClasses(testDiscovery()))->not->toContain(BaseAgent::class);
});

it('honours the discovery ignore list', function (): void {
    $discovery = testDiscovery(['ignore' => [HiddenAgent::class]]);

    expect(discoveredClasses($discovery))->not->toContain(HiddenAgent::class);
});

it('returns nothing when no configured path exists', function (): void {
    $discovery = testDiscovery(['paths' => [__DIR__ . '/does-not-exist']]);

    expect($discovery->all())->toBe([]);
});

it('resolves classes in a non-app namespace', function (): void {
    expect(discoveredClasses(testDiscovery()))->toContain(SupportAgent::class);
});

it('reads provider and model from attributes', function (): void {
    $agent = firstAgent(testDiscovery(), SupportAgent::class);

    expect($agent->provider)->toBe('openai')
        ->and($agent->model)->toBe('gpt-5.6-luna')
        ->and($agent->modelTier)->toBe('default');
});

it('reports the model tier when no explicit model is declared', function (): void {
    $agent = firstAgent(testDiscovery(), KitchenSinkAgent::class);

    expect($agent->model)->toBeNull()
        ->and($agent->modelTier)->toBe('smartest');
});

it('falls back to the configured default provider', function (): void {
    Configure::write('Ai.default', 'anthropic');

    $agent = firstAgent(testDiscovery(), KitchenSinkAgent::class);

    expect($agent->provider)->toBe('anthropic');
});

it('marks agents that cannot be instantiated as unavailable', function (): void {
    $discovery = testDiscovery();
    $broken = firstAgent($discovery, BrokenAgent::class);

    expect($broken->available)->toBeFalse()
        ->and($broken->errorKind)->toBe('binding')
        ->and($broken->error)->not->toBeEmpty()
        ->and($broken->unresolvable)->toHaveCount(1);

    expect(firstAgent($discovery, SupportAgent::class)->available)->toBeTrue();
});

it('builds a url-safe slug and finds an agent by it', function (): void {
    $discovery = testDiscovery();
    $agent = firstAgent($discovery, SupportAgent::class);

    expect($agent->slug)->toBe('test-app.ai.agents.support-agent')
        ->and($discovery->find($agent->slug)->class)->toBe(SupportAgent::class);
});

it('returns null for an unknown slug', function (): void {
    expect(testDiscovery()->find('nope.not.here'))->toBeNull();
});

it('resolves a slug to a live agent instance', function (): void {
    $discovery = testDiscovery();
    $agent = firstAgent($discovery, SupportAgent::class);

    expect($discovery->resolve($agent->slug))->toBeInstanceOf(SupportAgent::class)
        ->and($discovery->resolve('nope.not.here'))->toBeNull();
});

it('sorts agents by name', function (): void {
    $names = array_map(static fn(DiscoveredAgent $agent): string => $agent->name, testDiscovery()->all());
    $sorted = $names;
    sort($sorted);

    expect($names)->toBe($sorted);
});

it('finds an agent written after the first scan', function (): void {
    $dir = dirname(__DIR__, 2) . '/TestApp/Ai/Agents/Runtime';

    expect(discoveredClasses(testDiscovery()))->not->toContain('TestApp\\Ai\\Agents\\Runtime\\JustWrittenAgent');

    mkdir($dir, 0777, true);
    file_put_contents($dir . '/JustWrittenAgent.php', <<<'AGENT'
        <?php
        declare(strict_types=1);

        namespace TestApp\Ai\Agents\Runtime;

        use Crustum\Ai\Attributes\Provider;
        use Crustum\Ai\Contracts\Agent;
        use Crustum\Ai\Trait\PromptableTrait;
        use Stringable;

        #[Provider('openai')]
        class JustWrittenAgent implements Agent
        {
            use PromptableTrait;

            public function instructions(): Stringable|string
            {
                return 'Written while the app was already running.';
            }
        }
        AGENT);

    try {
        expect(discoveredClasses(testDiscovery()))->toContain('TestApp\\Ai\\Agents\\Runtime\\JustWrittenAgent');
    } finally {
        unlink($dir . '/JustWrittenAgent.php');
        rmdir($dir);
    }
});
