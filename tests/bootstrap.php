<?php
declare(strict_types=1);

$findRoot = function (): string {
    $root = dirname(__DIR__);
    if (is_dir($root . '/vendor/cakephp/cakephp')) {
        return $root;
    }

    $root = dirname(__DIR__, 2);
    if (is_dir($root . '/vendor/cakephp/cakephp')) {
        return $root;
    }

    $root = dirname(__DIR__, 3);
    if (is_dir($root . '/vendor/cakephp/cakephp')) {
        return $root;
    }

    return dirname(__DIR__);
};

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

define('ROOT', $findRoot());
define('APP_DIR', 'TestApp');
define('WEBROOT_DIR', 'webroot');
define('APP', ROOT . '/tests/TestApp/');
define('CONFIG', ROOT . '/tests/TestApp/config/');
define('WWW_ROOT', ROOT . DS . WEBROOT_DIR . DS);
define('TESTS', ROOT . DS . 'tests' . DS);
define('TMP', ROOT . DS . 'tmp' . DS);
define('LOGS', TMP . 'logs' . DS);
define('CACHE', TMP . 'cache' . DS);
define('SESSIONS', TMP . 'sessions' . DS);
define('RESOURCES', ROOT . DS . 'resources' . DS);
define('CAKE_CORE_INCLUDE_PATH', ROOT . '/vendor/cakephp/cakephp');
define('CORE_PATH', CAKE_CORE_INCLUDE_PATH . DS);
define('CAKE', CORE_PATH . 'src' . DS);

require ROOT . '/vendor/cakephp/cakephp/src/functions.php';
require ROOT . '/vendor/autoload.php';

// PHP 8.4 compatibility: pest-plugin-browser 4.3.1 calls
// ReflectionProperty::setValue() with a class-name string for the static
// PHPUnit Assert::$count, which is deprecated. Patch the installed file in
// place; drop this once the dependency is updated past 4.3.1.
$browserExecutionPath = ROOT . '/vendor/pestphp/pest-plugin-browser/src/Execution.php';
if (is_file($browserExecutionPath)) {
    $browserExecution = (string)file_get_contents($browserExecutionPath);
    $deprecatedCall = '$property->setValue(Assert::class, $originalCount)';
    if (str_contains($browserExecution, $deprecatedCall)) {
        file_put_contents(
            $browserExecutionPath,
            str_replace($deprecatedCall, '$property->setValue(null, $originalCount)', $browserExecution),
        );
    }
}

$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Container;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\Fixture\SchemaLoader;
use Crustum\Ai\Ai;
use Crustum\Ai\AiPlugin;
use League\Container\ReflectionContainer;

if (!function_exists('ensureDirectoryExists')) {
    /**
     * @param string $path Directory path
     * @return void
     */
    function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }
}

ensureDirectoryExists(TMP . 'cache/models');
ensureDirectoryExists(TMP . 'cache/persistent');
ensureDirectoryExists(TMP . 'cache/views');
ensureDirectoryExists(TMP . 'sessions');
ensureDirectoryExists(TMP . 'tests');
ensureDirectoryExists(LOGS);

Configure::write('debug', true);
Configure::write('App', [
    'namespace' => 'TestApp',
    'encoding' => 'UTF-8',
    'defaultLocale' => 'en_US',
    'defaultTimezone' => 'UTC',
    'base' => false,
    'dir' => 'src',
    'webroot' => 'webroot',
    'wwwRoot' => WWW_ROOT,
    'fullBaseUrl' => 'http://localhost',
    'paths' => [
        'plugins' => [ROOT . DS],
        'templates' => [APP . 'templates' . DS],
        'locales' => [RESOURCES . 'locales' . DS],
    ],
]);
Configure::write('Security', [
    'salt' => 'ai-nexus-test-security-salt-change-me',
]);
Configure::write('Session', [
    'defaults' => 'php',
]);

Cache::setConfig('_cake_translations_', [
    'className' => 'File',
    'path' => CACHE . 'persistent/',
    'prefix' => 'ai_nexus_test_translations_',
    'serialize' => true,
    'duration' => '+10 seconds',
]);
Cache::setConfig('_cake_model_', [
    'className' => 'File',
    'path' => CACHE . 'models/',
    'prefix' => 'ai_nexus_test_model_',
    'serialize' => true,
    'duration' => '+10 seconds',
]);

if (!getenv('db_dsn')) {
    putenv('db_dsn=sqlite:///:memory:');
}

ConnectionManager::setConfig('test', [
    'url' => getenv('db_dsn'),
    'timezone' => 'UTC',
]);
ConnectionManager::alias('test', 'default');

Configure::load('Crustum/AiNexus.ai_nexus', 'default');

$aiPlugin = new AiPlugin([
    'path' => ROOT . '/vendor/crustum/cakephp-ai' . DS,
]);
Plugin::getCollection()->add($aiPlugin);

$container = new Container();
$aiPlugin->services($container);
// Mirror production: Cake's Application delegates a ReflectionContainer so
// concrete agent classes auto-wire. Without this every discovered agent
// reports "not being managed by the container or delegates".
$container->delegate(new ReflectionContainer());
Ai::setContainer($container);

Configure::load('Crustum/Ai.ai', 'default');

$schemaLoader = new SchemaLoader();
$schemaLoader->loadInternalFile(TESTS . 'schema.php');

if (file_exists(CONFIG . 'bootstrap.php')) {
    require CONFIG . 'bootstrap.php';
}
