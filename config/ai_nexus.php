<?php
declare(strict_types=1);

/**
 * AiNexus Plugin Configuration
 *
 * Host applications copy settings into `config/ai_nexus.php` and load via
 * `Configure::load('ai_nexus')`. Keys are read as `Configure::read('AiNexus.*')`.
 */
return [
    'AiNexus' => [
        'enabled' => (bool)env('AI_NEXUS_ENABLED', env('APP_ENV', 'development') !== 'production'),
        'ui' => [
            'path' => env('AI_NEXUS_PATH', 'ai_nexus'),
            'middleware' => [],
        ],
        'assets' => [
            'path' => env('AI_NEXUS_ASSETS_PATH', 'ai_nexus-frontend'),
            'dir' => env('AI_NEXUS_ASSETS_DIR', null),
        ],
        'discovery' => [
            'paths' => [],
            'ignore' => [],
        ],
        'playground' => [
            'models' => [],
        ],
        'storage' => [
            'connection' => env('AI_NEXUS_DB_CONNECTION', null),
            'attachments_disk' => env('AI_NEXUS_ATTACHMENTS_DISK', 'local'),
        ],
        'retention' => [
            'auto_prune' => env('AI_NEXUS_AUTO_PRUNE', false),
            'days' => (int)env('AI_NEXUS_PRUNE_DAYS', 7),
        ],
    ],
];
