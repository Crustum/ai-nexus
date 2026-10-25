<?php
declare(strict_types=1);

namespace TestApp\Ai\Tools;

use Crustum\Ai\Contracts\Tool;
use Crustum\Ai\Tools\Request;
use Crustum\JsonSchema\Contracts\JsonSchema;
use RuntimeException;
use Stringable;

/**
 * Sample tool whose handler throws.
 */
class BrokenLedgerTool implements Tool
{
    /**
     * Describe the tool purpose.
     *
     * @return \Stringable|string
     */
    public function description(): Stringable|string
    {
        return 'Look up a ledger entry.';
    }

    /**
     * Fail handling on purpose.
     *
     * @param \Crustum\Ai\Tools\Request $request Tool invocation request.
     * @return \Stringable|string
     * @throws \RuntimeException Always.
     */
    public function handle(Request $request): Stringable|string
    {
        throw new RuntimeException('Ledger service unavailable');
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
            'entry' => $schema->string()->description('Ledger entry id')->required(),
        ];
    }
}
