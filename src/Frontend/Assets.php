<?php
declare(strict_types=1);

namespace Crustum\AiNexus\Frontend;

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Crustum\AiNexus\AiNexus;

/**
 * Resolves AiNexus SPA build URLs and disk paths.
 */
final class Assets
{
    /**
     * Main script file name.
     */
    public const string JS = 'app.js';

    /**
     * Main stylesheet file name.
     */
    public const string CSS = 'app.css';

    /**
     * Light theme stylesheet file name.
     */
    public const string STYLES_CSS = 'styles.css';

    /**
     * Dark theme stylesheet file name.
     */
    public const string STYLES_DARK_CSS = 'styles-dark.css';

    /**
     * Web path under host webroot (no leading/trailing slash).
     *
     * @return string
     */
    public static function urlPath(): string
    {
        $path = Configure::read('AiNexus.assets.path', 'ai_nexus-frontend');

        return trim(is_string($path) && $path !== '' ? $path : 'ai_nexus-frontend', '/');
    }

    /**
     * Absolute URL path for a build file (leading slash).
     *
     * @param string $file File name under the build folder.
     * @return string
     */
    public static function url(string $file): string
    {
        return '/' . self::urlPath() . '/' . ltrim($file, '/');
    }

    /**
     * Stylesheets to link in order.
     *
     * @return list<string>
     */
    public static function cssUrls(): array
    {
        return [self::versionedUrl(self::STYLES_CSS), self::versionedUrl(self::CSS)];
    }

    /**
     * Main SPA script URL.
     *
     * @return string
     */
    public static function jsUrl(): string
    {
        return self::versionedUrl(self::JS);
    }

    /**
     * Filesystem directory containing the build output.
     *
     * @return string
     */
    public static function diskPath(): string
    {
        $configured = Configure::read('AiNexus.assets.dir');

        if (is_string($configured) && $configured !== '') {
            if (preg_match('#^[a-zA-Z]:[\\\\/]#', $configured) === 1 || str_starts_with($configured, '/')) {
                return rtrim($configured, '/\\');
            }

            $root = defined('ROOT') ? (string)constant('ROOT') : dirname(__DIR__, 3);

            return rtrim($root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configured), '/\\');
        }

        if (defined('WWW_ROOT')) {
            $web = rtrim(constant('WWW_ROOT'), '/\\') . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, self::urlPath());

            if (is_dir($web)) {
                return $web;
            }
        }

        return rtrim(Plugin::path('Crustum/AiNexus'), '/\\')
            . DIRECTORY_SEPARATOR . 'webroot'
            . DIRECTORY_SEPARATOR . 'frontend';
    }

    /**
     * Absolute path to a build file on disk.
     *
     * @param string $file File name.
     * @return string
     */
    public static function diskFile(string $file): string
    {
        return self::diskPath() . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file), '/\\');
    }

    /**
     * Build URL with a cache-busting query derived from the file itself.
     *
     * Built filenames are stable (`app.js`, `styles.css`), so without this the
     * browser keeps running a stale bundle after a rebuild — including stale
     * behavior like the previous class-based theme toggle.
     *
     * @param string $file File name under the build folder.
     * @return string
     */
    protected static function versionedUrl(string $file): string
    {
        $path = self::diskFile($file);
        $version = is_file($path) ? (string)filemtime($path) : AiNexus::version();

        return self::url($file) . '?v=' . rawurlencode($version);
    }
}
