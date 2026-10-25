<?php
declare(strict_types=1);

use Crustum\AiNexus\AiNexus;

it('reports a version string', function (): void {
    expect(AiNexus::version())->toBeString()->not->toBe('');
});

it('shares one streaming rule between streams() and flushesUnder()', function (): void {
    expect(AiNexus::streams())->toBe(AiNexus::flushesUnder(PHP_SAPI));
});

it('does not flush under non-serving SAPIs', function (): void {
    expect(AiNexus::flushesUnder('cli'))->toBeFalse()
        ->and(AiNexus::flushesUnder('phpdbg'))->toBeFalse()
        ->and(AiNexus::flushesUnder('embed'))->toBeFalse();
});

it('flushes under serving SAPIs', function (): void {
    expect(AiNexus::flushesUnder('fpm-fcgi'))->toBeTrue()
        ->and(AiNexus::flushesUnder('apache2handler'))->toBeTrue();
});

it('exposes the frontend bootstrap variables', function (): void {
    $variables = AiNexus::scriptVariables();

    expect($variables)->toHaveKeys(['path', 'version', 'streaming'])
        ->and($variables['path'])->toBeString()
        ->and($variables['version'])->toBe(AiNexus::version())
        ->and($variables['streaming'])->toBe(AiNexus::streams());
});
