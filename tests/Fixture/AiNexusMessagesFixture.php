<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Dashboard conversation messages fixture.
 */
class AiNexusMessagesFixture extends TestFixture
{
    /**
     * @var string
     */
    public string $table = 'ainexus_messages';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $records = [];
}
