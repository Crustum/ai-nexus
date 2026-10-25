<?php
declare(strict_types=1);

namespace TestApp\Ai\Tools;

use Crustum\Ai\Contracts\Tool;
use Crustum\Ai\Tools\Request;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Stringable;

/**
 * Sample product search tool fixture.
 */
class SearchProductsTool implements Tool
{
    /**
     * Describe the tool purpose.
     *
     * @return \Stringable|string
     */
    public function description(): Stringable|string
    {
        return 'Search the product catalog for matching items.';
    }

    /**
     * Run the tool against static fixture data.
     *
     * @param \Crustum\Ai\Tools\Request $request Tool invocation request.
     * @return \Stringable|string
     */
    public function handle(Request $request): Stringable|string
    {
        $query = (string)($request['query'] ?? '');

        return (string)json_encode([
            ['id' => 42, 'name' => "Sony WH-1000 ({$query})"],
            ['id' => 87, 'name' => 'AirPods Max'],
        ]);
    }

    /**
     * Define the tool input schema.
     *
     * @param \Crustum\JsonSchema\Contracts\JsonSchema $schema Schema factory.
     * @return array<string, \Crustum\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Search query text')->required(),
            'max_results' => $schema->integer()->description('Maximum results to return'),
        ];
    }
}
