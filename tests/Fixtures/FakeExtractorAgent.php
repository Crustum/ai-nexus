<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Test\Fixtures;

use Crustum\Ai\Contracts\HasStructuredOutput;
use Crustum\JsonSchema\Contracts\JsonSchema;

/**
 * Sample structured-output agent double.
 */
class FakeExtractorAgent extends FakeSubAgent implements HasStructuredOutput
{
    /**
     * Define the structured output schema.
     *
     * @param \Crustum\JsonSchema\Contracts\JsonSchema $schema Schema factory.
     * @return array<string, \Crustum\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Extracted name')->required(),
            'nickname' => $schema->string()->description('Optional nickname'),
        ];
    }
}
