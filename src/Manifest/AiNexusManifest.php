<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Manifest;

use Crustum\AiNexus\AiNexusPlugin;
use Crustum\PluginManifest\Manifest\ManifestOps;

/**
 * Standalone manifest provider for the AiNexus plugin.
 *
 * Plain class without interface or trait dependencies, so the plugin loads
 * and runs without `crustum/plugin-manifest` installed. Discovered by the
 * installer through the provider convention when the plugin class itself
 * provides no manifest. All package references stay inside method bodies,
 * which only execute when the installer (and therefore the package) exists.
 */
final class AiNexusManifest
{
    /**
     * Plugin install assets via crustum/plugin-manifest.
     *
     * There is deliberately no install command: config and migrations ship
     * as manifest operations, and the manifest runner never overwrites
     * customized files.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function manifest(): array
    {
        $pluginPath = dirname(__DIR__, 2);
        $plugin = ManifestOps::pluginName(AiNexusPlugin::class);

        return array_merge(
            ManifestOps::manifestConfig(
                $plugin,
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'ai_nexus.php',
                CONFIG . 'ai_nexus.php',
                false,
            ),
            ManifestOps::manifestMigrations(
                $plugin,
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'Migrations',
            ),
            ManifestOps::manifestWebroot(
                $plugin,
                $pluginPath . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR . 'frontend',
                WWW_ROOT . 'ai_nexus-frontend',
            ),
            ManifestOps::manifestBootstrapAppend(
                $plugin,
                "if (file_exists(CONFIG . 'ai_nexus.php')) {\n    Configure::load('ai_nexus', 'default');\n}",
                '// AiNexus Plugin Configuration',
            ),
            ManifestOps::manifestStarRepo($plugin, 'Crustum/AiNexus'),
        );
    }
}
