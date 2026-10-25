<?php
declare(strict_types=1);

namespace TestApp\Ai\Tools;

use Crustum\Ai\Contracts\Tool;
use Crustum\Ai\Tools\Request;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Stringable;

/**
 * Sample tool that takes long enough to watch.
 */
class SlowTool implements Tool
{
    /**
     * Describe the tool purpose.
     *
     * @return \Stringable|string
     */
    public function description(): Stringable|string
    {
        return 'Query the slow analytics service. Takes a few seconds to respond.';
    }

    /**
     * Sleep for a clamped duration, then return static fixture data.
     *
     * @param \Crustum\Ai\Tools\Request $request Tool invocation request.
     * @return \Stringable|string
     */
    public function handle(Request $request): Stringable|string
    {
        $seconds = min(5.0, max(0.0, (float)($request['seconds'] ?? 3)));

        usleep((int)round($seconds * 1_000_000));

        return (string)json_encode(['waited_seconds' => $seconds, 'rows' => 42]);
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
            'seconds' => $schema->number()->description('How long the query should take, 0-5'),
        ];
    }
}
