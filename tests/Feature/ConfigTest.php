<?php
declare(strict_types=1);

use Cake\Core\Configure;

it('loads the plugin configuration with the full key set', function (): void {
    expect(Configure::check('AiNexus'))->toBeTrue();

    foreach (['enabled', 'ui', 'discovery', 'playground', 'storage', 'retention'] as $key) {
        expect(Configure::check("AiNexus.{$key}"))->toBeTrue("Missing AiNexus.{$key}");
    }
});

it('exposes the dashboard, discovery, storage, and retention settings', function (): void {
    expect(Configure::read('AiNexus.ui.path'))->toBeString()
        ->and(Configure::read('AiNexus.ui.middleware'))->toBeArray()
        ->and(Configure::read('AiNexus.discovery.paths'))->toBeArray()
        ->and(Configure::read('AiNexus.discovery.ignore'))->toBeArray()
        ->and(Configure::read('AiNexus.playground.models'))->toBeArray()
        ->and(Configure::read('AiNexus.storage.attachments_disk'))->toBeString()
        ->and(Configure::read('AiNexus.retention.days'))->toBeInt();
});
