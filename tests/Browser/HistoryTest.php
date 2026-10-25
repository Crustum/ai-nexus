<?php
declare(strict_types=1);

/*
| Targeting uses data-testid (`@name`); content is still asserted as real text,
| scoped to the element it belongs to.
|
| Conversations are seeded through the chat pipeline with faked agents, so the
| page reads exactly the data the product writes.
*/

use Crustum\Ai\Responses\Data\ToolCall;
use TestApp\Ai\Agents\SupportAgent;
use TestApp\Ai\Agents\WeatherAgent;

beforeEach(function (): void {
    useTestAppAgents();
});

/**
 * Seed a handful of conversations across two agents, one of which failed.
 *
 * One closure for both agents: every conversational agent shares a single fake
 * gateway, so two sets of responses would replace each other.
 *
 * @return void
 */
function seedHistory(): void
{
    $responses = fn(string $prompt): string => str_contains($prompt, 'fails')
        ? throw new RuntimeException('Provider exploded')
        : 'Answered.';

    fakeChatAgent(SupportAgent::class, $responses);
    fakeChatAgent(WeatherAgent::class, $responses);

    sendMessage('test-app.ai.agents.support-agent', 'Find me a hoodie');
    sendMessage('test-app.ai.agents.support-agent', 'This one fails');
    sendMessage('test-app.ai.agents.weather-agent', 'Weather in Tbilisi');
}

it('shows an empty state before anything has happened', function (): void {
    visit('/ai_nexus/history')
        ->assertPresent('@history-empty')
        ->assertSeeIn('@history-empty', 'No conversations yet')
        ->assertMissing('@history-table')
        ->assertNoJavaScriptErrors();
});

it('lists conversations with their agent, status and tokens', function (): void {
    seedHistory();

    $page = visit('/ai_nexus/history');

    $page->assertPresent('@history-table')
        ->assertSeeIn('@history-table', 'Find me a hoodie')
        ->assertSeeIn('@history-table', 'Weather in Tbilisi')
        ->assertNoJavaScriptErrors();

    // Three rows, one of which failed.
    expect($page->script("document.querySelectorAll('[data-testid=history-row]').length"))->toBe(3);
    expect($page->script("document.querySelectorAll('[data-status=error]').length"))->toBe(1);
});

it('counts the conversations beside the title', function (): void {
    seedHistory();

    visit('/ai_nexus/history')->assertSee('(3)');
});

it('narrows the list by search', function (): void {
    seedHistory();

    $page = visit('/ai_nexus/history');

    $page->type('@history-search', 'hoodie');

    // Element assertions wait for the debounce and re-render; a text assertion
    // would read the pre-filter DOM and pass for the wrong reason.
    $page->assertMissing('[data-agent="test-app.ai.agents.weather-agent"]')
        ->assertPresent('[data-agent="test-app.ai.agents.support-agent"]')
        ->assertSeeIn('@history-table', 'Find me a hoodie')
        ->assertNoJavaScriptErrors();
});

it('shows a distinct state when nothing matches the filters', function (): void {
    seedHistory();

    $page = visit('/ai_nexus/history');

    $page->type('@history-search', 'nothing will match this');

    // Different from "nothing yet": this one offers a way back.
    $page->assertPresent('@history-no-matches')
        ->assertSeeIn('@history-no-matches', 'No conversations match these filters')
        ->assertMissing('@history-empty');

    $page->click('Clear the filters');

    $page->assertPresent('@history-table');
});

it('filters by status and badges the active filter', function (): void {
    seedHistory();

    $page = visit('/ai_nexus/history');

    $page->click('[data-testid=filter-status]');
    $page->click('Error');

    $page->assertMissing('[data-agent="test-app.ai.agents.weather-agent"]')
        ->assertSeeIn('@history-table', 'This one fails')
        // The badge is what tells you the list is narrowed.
        ->assertPresent('[aria-label="1 selected"]');
});

it('keeps filters through a reload', function (): void {
    seedHistory();

    $page = visit('/ai_nexus/history');

    $page->type('@history-search', 'hoodie');
    $page->assertSeeIn('@history-table', 'Find me a hoodie');

    // Filters live in the URL, so a reload restores the same view.
    $page->refresh();

    $page->assertMissing('[data-agent="test-app.ai.agents.weather-agent"]')
        ->assertSeeIn('@history-table', 'Find me a hoodie');

    expect($page->script("document.querySelector('[data-testid=history-search]').value"))
        ->toBe('hoodie');
});

it('opens a conversation with its thread intact', function (): void {
    fakeChatAgent(SupportAgent::class, [
        new ToolCall(id: 'call_1', name: 'SearchProductsTool', arguments: ['query' => 'hoodie']),
        'Found three matches.',
    ]);

    sendMessage('test-app.ai.agents.support-agent', 'Find me a hoodie');

    $page = visit('/ai_nexus/history');

    $page->click('Find me a hoodie');

    $page->assertPathContains('/playground/')
        ->assertSeeIn('@message-user', 'Find me a hoodie')
        ->assertSeeIn('@message-assistant', 'Found three matches.')
        ->assertPresent('@tool-card')
        ->assertNoJavaScriptErrors();
});

it('renames a conversation from the row menu', function (): void {
    seedHistory();

    $page = visit('/ai_nexus/history');

    $page->click('[aria-label="Actions for Find me a hoodie"]');
    $page->click('Rename');

    $page->assertPresent('@rename-dialog');

    $page->type('[aria-label="Conversation name"]', 'Hoodie search, take three');
    $page->click('Save');

    $page->assertSeeIn('@history-table', 'Hoodie search, take three')
        ->assertNoJavaScriptErrors();
});

it('deletes a conversation after confirming', function (): void {
    seedHistory();

    $page = visit('/ai_nexus/history');

    $page->click('[aria-label="Actions for Weather in Tbilisi"]');
    $page->click('Delete');

    $page->assertPresent('@delete-dialog')
        ->assertSeeIn('@delete-dialog', 'cannot be undone');

    $page->click('[aria-label="Confirm delete"]');

    $page->assertMissing('[data-agent="test-app.ai.agents.weather-agent"]');

    expect(chatTables()[0]->find()->count())->toBe(2);
});

it('lists recent conversations in the sidebar with an error indicator', function (): void {
    seedHistory();

    $page = visit('/ai_nexus/history');

    $page->assertPresent('@sidebar-conversations')
        ->assertSeeIn('@sidebar-conversations', 'Weather in Tbilisi');

    // The failed conversation is flagged — getting back to the run that broke is
    // the point of a recents list on a debugging tool.
    expect($page->script(
        "document.querySelectorAll('[data-testid=sidebar-conversations] [aria-label=\"This conversation contains an error\"]').length",
    ))->toBe(1);
});

it('renames a conversation from the sidebar menu', function (): void {
    seedHistory();

    $page = visit('/ai_nexus/history');

    $page->click('[aria-label="Recent conversation actions for Find me a hoodie"]');
    $page->click('Rename');

    $page->assertPresent('@rename-dialog');
    $page->type('[aria-label="Conversation name"]', 'Renamed from the sidebar');
    $page->click('Save');

    // Both lists read the same records, so both have to show the new title —
    // the sidebar going stale after a write is the bug this shares its
    // implementation with History to avoid.
    $page->assertSeeIn('@sidebar-conversations', 'Renamed from the sidebar')
        ->assertSeeIn('@history-table', 'Renamed from the sidebar')
        ->assertNoJavaScriptErrors();
});

it('deletes a conversation from the sidebar after confirming', function (): void {
    seedHistory();

    $page = visit('/ai_nexus/history');

    $page->click('[aria-label="Recent conversation actions for Weather in Tbilisi"]');
    $page->click('Delete');

    $page->assertPresent('@delete-dialog');
    $page->click('[aria-label="Confirm delete"]');

    $page->assertMissing('[data-agent="test-app.ai.agents.weather-agent"]');

    expect(chatTables()[0]->find()->count())->toBe(2);
});

it('returns you to the conversation you were last in', function (): void {
    fakeChatAgent(SupportAgent::class, ['Answered.']);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');
    $page->type('@composer-input', 'Find me a hoodie')->click('Send');
    $page->assertSeeIn('@message-assistant', 'Answered.');

    // Leave and come back the way you would from Discovery.
    $page->click('Discovery');
    $page->click('SupportAgent');

    $page->assertSeeIn('@message-user', 'Find me a hoodie')->assertNoJavaScriptErrors();
});

it('stays blank when a fresh thread is what you chose', function (): void {
    fakeChatAgent(SupportAgent::class, ['Answered.']);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');
    $page->type('@composer-input', 'Find me a hoodie')->click('Send');
    $page->assertSeeIn('@message-assistant', 'Answered.');

    $page->click('[aria-label="Conversation actions"]');
    $page->click('New conversation');
    $page->assertPresent('@chat-empty');

    // A deliberately blank page must stay blank on return.
    $page->click('Discovery');
    $page->click('SupportAgent');

    $page->assertPresent('@chat-empty')->assertNoJavaScriptErrors();
});

it('renders history in both themes', function (): void {
    seedHistory();

    foreach (['Light', 'Dark'] as $theme) {
        $page = visit('/ai_nexus/history');

        $page->click('System')->click($theme);

        $page->assertPresent('@history-table')->assertNoJavaScriptErrors();
    }
});
