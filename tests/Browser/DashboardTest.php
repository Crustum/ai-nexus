<?php
declare(strict_types=1);

/*
| Browser (end-to-end) smoke tests.
|
| These drive a real browser against the dashboard to verify the parts no PHP
| test can reach: that the compiled Vue app actually mounts, renders, and
| routes. Run with `composer test:e2e` (requires `npm run build` + Playwright).
*/

it('mounts the vue app and renders the sidebar shell', function (): void {
    visit('/ai_nexus')
        ->assertSee('AiNexus')
        ->assertSee('Discovery')
        ->assertSee('History')
        ->assertNoJavaScriptErrors();
});

it('renders the discovery page as the index route', function (): void {
    visit('/ai_nexus')
        ->assertSee('Agents')
        ->assertSee('Click a card to open the chat playground.');
});

it('navigates to history via the sidebar without a full page load', function (): void {
    visit('/ai_nexus')
        ->click('History')
        ->assertPathIs('/ai_nexus/history')
        ->assertPresent('@history-empty')
        ->assertNoJavaScriptErrors();
});

it('serves a deep-linked route through the spa catch-all', function (): void {
    visit('/ai_nexus/history')
        ->assertSee('History')
        ->assertNoJavaScriptErrors();
});
