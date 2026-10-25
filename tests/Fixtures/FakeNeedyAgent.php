<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Test\Fixtures;

/**
 * Sample agent whose constructor dependency cannot be autowired.
 */
class FakeNeedyAgent extends FakeSubAgent
{
    /**
     * @param \Crustum\AiNexus\Test\Fixtures\FakeUnboundInterface $dependency Unresolvable dependency.
     */
    public function __construct(protected FakeUnboundInterface $dependency)
    {
    }
}
