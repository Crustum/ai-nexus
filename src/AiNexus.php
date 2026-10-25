<?php
declare(strict_types=1);

namespace Crustum\AiNexus;

use Cake\Core\Configure;
use Composer\InstalledVersions;
use OutOfBoundsException;

/**
 * Static value surface for the AiNexus plugin.
 *
 * Holds no services and touches no container: version metadata, the streaming
 * capability rule shared with the chat emitter, and the frontend bootstrap
 * variables.
 *
 * Access control is deliberately not part of this surface. The host
 * application owns authentication and authorization (for example cakedc/users
 * on top of cakephp/authorization) and applies its own middleware to the
 * dashboard scope through the `AiNexus.ui.middleware` configuration key.
 */
class AiNexus
{
    /**
     * Fallback version, used only when Composer metadata is unavailable.
     */
    public const VERSION = '0.1.0';

    /**
     * The installed AiNexus version.
     *
     * @return string
     */
    public static function version(): string
    {
        if (!class_exists(InstalledVersions::class)) {
            return static::VERSION;
        }

        try {
            return ltrim(InstalledVersions::getPrettyVersion('crustum/ai-nexus') ?? static::VERSION, 'v');
        } catch (OutOfBoundsException) {
            return static::VERSION;
        }
    }

    /**
     * Whether this deployment can stream a response as it is produced.
     *
     * @return bool
     */
    public static function streams(): bool
    {
        return static::flushesUnder(PHP_SAPI);
    }

    /**
     * Whether a given SAPI can push bytes to a client as they are written.
     *
     * @param string $sapi SAPI name.
     * @return bool
     */
    public static function flushesUnder(string $sapi): bool
    {
        return !in_array($sapi, ['cli', 'phpdbg', 'embed'], true);
    }

    /**
     * The variables exposed to the frontend via window.AiNexus.
     *
     * @return array<string, mixed>
     */
    public static function scriptVariables(): array
    {
        return [
            'path' => (string)Configure::read('AiNexus.ui.path', 'ai_nexus'),
            'version' => static::version(),
            'streaming' => static::streams(),
        ];
    }
}
