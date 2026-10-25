<?php
declare(strict_types=1);

/*
| Theme switching through the sidebar switcher, persisted per browser.
*/

it('switches the theme from the sidebar switcher', function (): void {
    $page = visit('/ai_nexus');

    // The trigger shows the current theme; default is System.
    $page->click('System')
        ->click('Dark');

    // Dark mode is a separate stylesheet toggled by its media attribute
    // (see resources/frontend/js/composables/useTheme.js), not a class.
    expect($page->script("document.querySelector('style[data-scheme=\"dark\"]').media"))->not->toBe('max-width: 1px');
    expect($page->script("localStorage.getItem('ai-nexus-theme')"))->toBe('dark');
});

it('restores the stored theme on reload', function (): void {
    // Each visit() gets a fresh browser context, so reload in place to keep
    // the stored preference — this exercises the layout's inline script.
    $page = visit('/ai_nexus');
    $page->script("localStorage.setItem('ai-nexus-theme', 'dark')");
    $page->refresh();

    expect($page->script("document.querySelector('style[data-scheme=\"dark\"]').media"))->not->toBe('max-width: 1px');
});

it('applies the light theme when chosen', function (): void {
    $page = visit('/ai_nexus');

    $page->click('System')->click('Light');

    expect($page->script("document.querySelector('style[data-scheme=\"dark\"]').media"))->toBe('max-width: 1px');
    expect($page->script('document.documentElement.className'))->not->toContain('dark');
    expect($page->script("localStorage.getItem('ai-nexus-theme')"))->toBe('light');
});
