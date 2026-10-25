<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Test\Fixtures;

use Crustum\Ai\Contracts\Tool;
use Crustum\Ai\Tools\Request;
use Crustum\JsonSchema\Contracts\JsonSchema;
use RuntimeException;
use Stringable;

/**
 * Sample tool whose schema cannot be built.
 */
class FakeBrokenSchemaTool implements Tool
{
    /**
     * Describe the tool purpose.
     *
     * @return \Stringable|string
     */
    public function description(): Stringable|string
    {
        return 'Its schema cannot be built.';
    }

    /**
     * Run the tool.
     *
     * @param \Crustum\Ai\Tools\Request $request Tool invocation request.
     * @return \Stringable|string
     */
    public function handle(Request $request): Stringable|string
    {
        return '';
    }

    /**
     * Fail schema building on purpose.
     *
     * @param \Crustum\JsonSchema\Contracts\JsonSchema $schema Schema factory.
     * @return array<string, \Crustum\JsonSchema\Types\Type>
     * @throws \RuntimeException Always.
     */
    public function schema(JsonSchema $schema): array
    {
        throw new RuntimeException('Schema could not be built.');
    }
}
