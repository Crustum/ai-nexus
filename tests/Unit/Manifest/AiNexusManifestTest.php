<?php
declare(strict_types=1);

use Crustum\AiNexus\AiNexusPlugin;
use Crustum\AiNexus\Manifest\AiNexusManifest;
use Crustum\PluginManifest\Manifest\ManifestResolver;

it('emits config, migrations, webroot, bootstrap and star operations', function (): void {
    $tags = array_column(AiNexusManifest::manifest(), 'tag');

    expect($tags)->toContain('config')
        ->and($tags)->toContain('migrations')
        ->and($tags)->toContain('webroot')
        ->and($tags)->toContain('bootstrap')
        ->and($tags)->toContain('star-repo');
});

it('is discoverable through the resolver', function (): void {
    expect(ManifestResolver::providerClass(AiNexusPlugin::class))->toBe(AiNexusManifest::class)
        ->and(ManifestResolver::manifestFor(AiNexusPlugin::class))->toBe(AiNexusManifest::manifest());
});

it('leaves the plugin class without a manifest contract', function (): void {
    expect(method_exists(AiNexusPlugin::class, 'manifest'))->toBeFalse();
});
