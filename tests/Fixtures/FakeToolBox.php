<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Test\Fixtures;

use Crustum\Ai\Contracts\HasTools;
use stdClass;

/**
 * Sample tool holder mixing a working tool, a broken tool, and an unknown value.
 */
class FakeToolBox implements HasTools
{
    /**
     * List the exposed tool values.
     *
     * @return iterable
     */
    public function tools(): iterable
    {
        return [
            new FakeSearchTool(),
            new FakeBrokenSchemaTool(),
            new stdClass(),
        ];
    }
}
