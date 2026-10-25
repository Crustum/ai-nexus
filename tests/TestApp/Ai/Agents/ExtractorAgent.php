<?php
declare(strict_types=1);

namespace TestApp\Ai\Agents;

use Crustum\Ai\Attributes\Model;
use Crustum\Ai\Attributes\Provider;
use Crustum\Ai\Contracts\Agent;
use Crustum\Ai\Contracts\HasStructuredOutput;
use Crustum\Ai\Trait\PromptableTrait;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Stringable;

/**
 * Structured-output agent fixture.
 */
#[Provider('openai')]
#[Model('gpt-5.6-luna')]
class ExtractorAgent implements Agent, HasStructuredOutput
{
    use PromptableTrait;

    /**
     * Describe the agent behavior.
     *
     * @return \Stringable|string
     */
    public function instructions(): Stringable|string
    {
        return 'Extract structured contact details from the given text.';
    }

    /**
     * Define the structured output schema.
     *
     * @param \Crustum\JsonSchema\Contracts\JsonSchema $schema Schema factory.
     * @return array<string, \Crustum\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Full name')->required(),
            'email' => $schema->string()->description('Email address'),
            'company' => $schema->string()->description('Company name'),
        ];
    }
}
