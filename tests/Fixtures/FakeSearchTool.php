<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Test\Fixtures;

use Crustum\Ai\Contracts\Tool;
use Crustum\Ai\Tools\Request;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Stringable;

/**
 * Sample user tool with one required and one optional parameter.
 */
class FakeSearchTool implements Tool
{
    /**
     * Describe the tool purpose.
     *
     * @return \Stringable|string
     */
    public function description(): Stringable|string
    {
        return 'Search the product catalog.';
    }

    /**
     * Run the tool against static fixture data.
     *
     * @param \Crustum\Ai\Tools\Request $request Tool invocation request.
     * @return \Stringable|string
     */
    public function handle(Request $request): Stringable|string
    {
        return (string)json_encode(['results' => []]);
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
            'max_results' => $schema->integer()->description('Maximum result count'),
        ];
    }
}
