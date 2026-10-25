<?php
declare(strict_types=1);

namespace TestApp\Ai\Tools;

use Crustum\Ai\Contracts\Tool;
use Crustum\Ai\Tools\Request;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Stringable;

/**
 * Sample weather tool fixture.
 */
class GetWeatherTool implements Tool
{
    /**
     * Describe the tool purpose.
     *
     * @return \Stringable|string
     */
    public function description(): Stringable|string
    {
        return 'Get the current weather for a city.';
    }

    /**
     * Run the tool against static fixture data.
     *
     * @param \Crustum\Ai\Tools\Request $request Tool invocation request.
     * @return \Stringable|string
     */
    public function handle(Request $request): Stringable|string
    {
        $city = (string)($request['city'] ?? 'unknown');

        return (string)json_encode(['city' => $city, 'temp_c' => 21, 'conditions' => 'clear']);
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
            'city' => $schema->string()->description('City name')->required(),
        ];
    }
}
