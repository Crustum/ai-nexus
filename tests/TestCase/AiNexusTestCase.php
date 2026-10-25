<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Test\TestCase;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * Base test case for AiNexus database tests.
 */
abstract class AiNexusTestCase extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use IntegrationTestTrait;

    /**
     * Fixtures shared by every database test so tables are truncated between tests.
     *
     * @var array<int, string>
     */
    protected array $fixtures = [
        'plugin.Crustum/AiNexus.AiNexusConversations',
        'plugin.Crustum/AiNexus.AiNexusMessages',
        'plugin.Crustum/AiNexus.AiNexusToolInvocations',
    ];

    /**
     * Restore the open dashboard (no host middleware) afterwards.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Configure::write('AiNexus.ui.middleware', []);

        parent::tearDown();
    }
}
