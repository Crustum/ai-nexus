<?php
declare(strict_types=1);

/*
| Targeting uses data-testid (`@name`); content is still asserted as real text,
| scoped to the element it belongs to.
*/

beforeEach(function (): void {
    useTestAppAgents();
});

it('opens the info panel from an agent card', function (): void {
    visit('/ai_nexus')
        ->click('Info')
        ->assertPathContains('/playground/')
        ->assertPresent('@info-panel')
        ->assertNoJavaScriptErrors();
});

it('opens directly from a deep link with the panel already showing', function (): void {
    visit('/ai_nexus/playground/test-app.ai.agents.configured-agent?info=1')
        ->assertSee('ConfiguredAgent')
        // Scoped assertions run in Playwright strict mode, so each string must
        // match exactly one node — 'PROVIDER' would also hit 'PROVIDER OPTIONS'.
        ->assertSeeIn('@info-panel', 'Class')
        ->assertSeeIn('@info-panel', 'Temperature')
        ->assertNoJavaScriptErrors();
});

it('shows every generation setting the agent declares', function (): void {
    $page = visit('/ai_nexus/playground/test-app.ai.agents.configured-agent?info=config');

    foreach (['Temperature', '0.7', 'Max_Tokens', 'Top_P', 'Tool_Choice', 'required', '45s', 'Enabled'] as $expected) {
        $page->assertSeeIn('@info-panel', $expected);
    }
});

it('lists provider options and middleware', function (): void {
    visit('/ai_nexus/playground/test-app.ai.agents.configured-agent?info=config')
        ->assertSeeIn('@info-panel', 'reasoning_effort')
        ->assertSeeIn('@info-panel', 'PiiRedactor');
});

it('renders the system prompt as markdown', function (): void {
    visit('/ai_nexus/playground/test-app.ai.agents.configured-agent?info=prompt')
        ->assertSeeIn('@prompt', 'Configured agent')
        ->assertSeeIn('@prompt', 'Every generation option is set explicitly')
        ->assertNoJavaScriptErrors();
});

it('shows tool parameters with their types', function (): void {
    visit('/ai_nexus/playground/test-app.ai.agents.support-agent?info=tools')
        ->assertSeeIn('@tool-detail', 'SEARCHPRODUCTSTOOL')
        ->assertSeeIn('@tool-detail', 'max_results')
        ->assertSeeIn('@tool-detail', 'Integer')
        ->assertSeeIn('@tool-detail', 'Search query text');
});

it('marks a provider tool and a sub-agent tool distinctly', function (): void {
    visit('/ai_nexus/playground/test-app.ai.agents.kitchen-sink-agent?info=tools')
        ->assertSee('Provider tool')
        ->assertSee('Agent tool')
        ->assertSee('Inspect WeatherAgent');
});

it('degrades a tool whose schema cannot be built', function (): void {
    visit('/ai_nexus/playground/test-app.ai.agents.configured-agent?info=tools')
        ->assertSee('Schema unavailable')
        // The healthy tool alongside it still renders.
        ->assertSee('Search query text');
});

it('shows the output schema for a structured-output agent', function (): void {
    visit('/ai_nexus/playground/test-app.ai.agents.extractor-agent?info=tools')
        ->assertSeeIn('@output-schema', 'Email address');
});

it('shows an empty state when the agent has no tools', function (): void {
    visit('/ai_nexus/playground/test-app.ai.agents.hidden-agent?info=tools')
        ->assertPresent('@tools-empty');
});

it('closes the panel and reopens it from the header', function (): void {
    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent?info=1');

    $page->assertPresent('@info-panel');

    $page->click('[aria-label="Close info panel"]');
    $page->assertMissing('@info-panel');

    $page->click('[aria-label="Open info panel"]');
    $page->assertPresent('@info-panel')->assertNoJavaScriptErrors();
});

it('explains an unavailable agent inside the panel', function (): void {
    visit('/ai_nexus/playground/test-app.ai.agents.broken-agent?info=1')
        ->assertSeeIn('@unresolvable-hint', 'construct this agent')
        ->assertSeeIn('@unresolvable-hint', '$this->app->bind(');
});

it('shows a not-found state for an unknown agent', function (): void {
    visit('/ai_nexus/playground/nope.not.here')
        ->assertSeeIn('@empty-state', 'Agent not found')
        ->assertNoJavaScriptErrors();
});
