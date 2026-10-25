<?php
declare(strict_types=1);

use Crustum\Ai\Contracts\HasTools;
use Crustum\AiNexus\Discovery\AgentSlug;
use Crustum\AiNexus\Discovery\ToolDetail;
use Crustum\AiNexus\Test\Fixtures\FakeExtractorAgent;
use Crustum\AiNexus\Test\Fixtures\FakeProviderTool;
use Crustum\AiNexus\Test\Fixtures\FakeSubAgent;
use Crustum\AiNexus\Test\Fixtures\FakeToolBox;
use function Cake\Collection\collection;

it('returns no tools for an object without tools', function (): void {
    expect((new ToolDetail())->all(new stdClass()))->toBe([]);
});

it('describes a user tool with parameters and required flags', function (): void {
    $tools = (new ToolDetail())->all(new FakeToolBox());

    $tool = collection($tools)->firstMatch(['name' => 'FakeSearchTool']);

    expect($tool['type'])->toBe('tool')
        ->and($tool['description'])->toContain('product catalog')
        ->and($tool['schema_error'])->toBeNull();

    $parameters = collection($tool['parameters']);

    expect($parameters->firstMatch(['name' => 'query']))->toBe([
        'name' => 'query',
        'type' => 'string',
        'description' => 'Search query text',
        'required' => true,
    ])->and($parameters->firstMatch(['name' => 'max_results'])['required'])->toBeFalse();
});

it('degrades a tool whose schema throws without breaking the others', function (): void {
    $tools = (new ToolDetail())->all(new FakeToolBox());

    $broken = collection($tools)->firstMatch(['name' => 'FakeBrokenSchemaTool']);

    expect($broken['type'])->toBe('tool')
        ->and($broken['description'])->toContain('cannot be built')
        ->and($broken['parameters'])->toBe([])
        ->and($broken['schema_error'])->toContain('Schema could not be built');

    expect(collection($tools)->firstMatch(['name' => 'FakeSearchTool'])['parameters'])->not->toBeEmpty();
});

it('marks values of unknown kind without touching accessors', function (): void {
    $tools = (new ToolDetail())->all(new FakeToolBox());

    $unknown = collection($tools)->firstMatch(['name' => 'stdClass']);

    expect($unknown['type'])->toBe('unknown')
        ->and($unknown['description'])->toBeNull()
        ->and($unknown['parameters'])->toBe([]);
});

it('links a sub-agent tool to its own slug', function (): void {
    $box = new class implements HasTools {
        public function tools(): iterable
        {
            return [new FakeSubAgent()];
        }
    };

    $tools = (new ToolDetail())->all($box);

    expect($tools)->toHaveCount(1)
        ->and($tools[0]['type'])->toBe('agent')
        ->and($tools[0]['name'])->toBe('FakeSubAgent')
        ->and($tools[0]['agent_slug'])->toBe(AgentSlug::make(FakeSubAgent::class));
});

it('describes a provider tool without calling description or schema', function (): void {
    $box = new class implements HasTools {
        public function tools(): iterable
        {
            return [new FakeProviderTool()];
        }
    };

    $tools = (new ToolDetail())->all($box, 'openai');

    expect($tools)->toHaveCount(1)
        ->and($tools[0]['type'])->toBe('provider_tool')
        ->and($tools[0]['name'])->toBe('FakeProviderTool')
        ->and($tools[0]['description'])->toBeNull()
        ->and($tools[0]['parameters'])->toBe([])
        ->and($tools[0]['provider_options'])->toBeArray();
});

it('returns the output schema only for structured-output agents', function (): void {
    $detail = new ToolDetail();

    $schema = $detail->outputSchema(new FakeExtractorAgent());

    expect(collection($schema)->firstMatch(['name' => 'name']))->toMatchArray([
        'type' => 'string',
        'required' => true,
    ])->and(collection($schema)->firstMatch(['name' => 'nickname'])['required'])->toBeFalse();

    expect($detail->outputSchema(new FakeSubAgent()))->toBeNull();
});
