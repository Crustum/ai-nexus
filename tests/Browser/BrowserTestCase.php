<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Test\Browser;

use Cake\Core\Configure;
use Crustum\AiNexus\Test\Browser\Support\CakeHttpServer;
use Crustum\AiNexus\Test\TestCase\AiNexusTestCase;
use Pest\Browser\ServerManager;
use Pest\Browser\Support\Port;
use ReflectionProperty;

/**
 * Base class for browser (end-to-end) tests.
 *
 * The dashboard assets are served from the built `webroot/frontend`
 * directory, so the tests only need a built bundle. The dashboard gate is
 * opened explicitly: access control belongs to the host application, and
 * there is no host stack in the test process.
 */
abstract class BrowserTestCase extends AiNexusTestCase
{
    /**
     * Whether the in-process driver has been installed.
     */
    protected static bool $driverInstalled = false;

    /**
     * Install the driver and open the dashboard gate.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!is_file(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'app.js')) {
            $this->markTestSkipped('AiNexus assets are not built. Run `npm run build` first.');
        }

        Configure::write('AiNexus.ui.middleware', []);
        useTestAppAgents();

        if (!static::$driverInstalled) {
            $manager = ServerManager::instance();
            $property = new ReflectionProperty(ServerManager::class, 'http');
            $property->setValue($manager, new CakeHttpServer('127.0.0.1', Port::find()));
            static::$driverInstalled = true;
        }
    }
}
