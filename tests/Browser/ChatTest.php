<?php
declare(strict_types=1);

/*
| Targeting uses data-testid (`@name`); content is still asserted as real text,
| scoped to the element it belongs to.
|
| Every agent here is faked, so nothing reaches a provider: no API spend, no
| flakiness, and the assertions are about AiNexus rather than about a model.
*/

use Cake\Core\Configure;
use Crustum\Ai\Responses\Data\Meta;
use Crustum\Ai\Responses\Data\TextUsage;
use Crustum\Ai\Responses\Data\ToolCall;
use Crustum\Ai\Responses\TextResponse;
use TestApp\Ai\Agents\ExtractorAgent;
use TestApp\Ai\Agents\FlakyToolAgent;
use TestApp\Ai\Agents\SlowToolAgent;
use TestApp\Ai\Agents\SupportAgent;
use TestApp\Ai\Agents\WeatherAgent;

beforeEach(function (): void {
    useTestAppAgents();
});

it('shows the hero state before the first message', function (): void {
    visit('/ai_nexus/playground/test-app.ai.agents.support-agent')
        ->assertPresent('@chat-empty')
        ->assertSeeIn('@chat-empty', 'Explore, test, and debug your AI agents')
        ->assertPresent('@chat-composer')
        ->assertNoJavaScriptErrors();
});

it('sends a message and renders the streamed answer', function (): void {
    fakeChatAgent(SupportAgent::class, ['Returns are accepted within thirty days.']);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');

    $page->type('@composer-input', 'What is your return policy?')
        ->click('Send');

    $page->assertSeeIn('@message-user', 'What is your return policy?')
        ->assertSeeIn('@message-assistant', 'Returns are accepted within thirty days.')
        ->assertMissing('@chat-empty')
        ->assertNoJavaScriptErrors();
});

it('shows a loading indicator before the first stream event arrives', function (): void {
    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');

    $page->script(<<<'JS'
        (() => {
            window.fetch = () => new Promise(() => {});
        })()
    JS);

    $page->type('@composer-input', 'Hello?')->click('Send');

    $page->assertSeeIn('@message-user', 'Hello?')
        ->assertPresent('@message-assistant-loading')
        ->assertSeeIn('@message-assistant-loading', 'Loading...')
        ->assertNoJavaScriptErrors();
});

it('replaces the loading indicator without duplicating the assistant message', function (): void {
    fakeChatAgent(SupportAgent::class, ['Returns are accepted within thirty days.']);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');

    $page->type('@composer-input', 'What is your return policy?')
        ->click('Send');

    $page->assertSeeIn('@message-assistant', 'Returns are accepted within thirty days.')
        ->assertMissing('@message-assistant-loading')
        ->assertNoJavaScriptErrors();

    expect($page->script("document.querySelectorAll('[data-testid=message-assistant]').length"))->toBe(1);
});

it('clears the loading indicator when the request fails before streaming starts', function (): void {
    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');

    $page->script(<<<'JS'
        (() => {
            window.fetch = () => Promise.reject(new Error('Network unavailable.'));
        })()
    JS);

    $page->type('@composer-input', 'Hello?')->click('Send');

    $page->assertPresent('@error-card')
        ->assertSeeIn('@error-card', 'Network unavailable.')
        ->assertMissing('@message-assistant-loading')
        ->assertNoJavaScriptErrors();
});

it('preserves reasoning when a later stream event clears the loading placeholder', function (array $event): void {
    fakeChatAgent(SupportAgent::class, ['Unused fallback.']);
    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');
    $parts = json_encode([
        ['type' => 'reasoning-start'],
        ['type' => 'reasoning-delta', 'delta' => 'First I check the catalog.'],
        ['type' => 'reasoning-end'],
        $event,
    ]);

    // The SDK fake cannot emit reasoning events; supply those SSE parts at the transport boundary.
    $page->script(<<<JS
        (() => {
            const originalFetch = window.fetch;
            window.fetch = (url, options) => {
                if (!String(url).includes('/api/chat/')) return originalFetch(url, options);
                const body = {$parts}.map(part => 'data: ' + JSON.stringify(part) + '\\n\\n').join('');
                return Promise.resolve(new Response(body, { headers: { 'Content-Type': 'text/event-stream' } }));
            };
        })()
    JS);

    $page->type('@composer-input', 'Check the catalog')->click('Send');

    // The toggle also renders the chevron, so its exact text is never just
    // "Thinking" — target the button explicitly. (Text clicks hang instead of
    // failing when nothing matches exactly, so guard with assertPresent.)
    $page->assertPresent('@reasoning')
        ->assertPresent('[data-testid="reasoning"] button');
    $page->click('[data-testid="reasoning"] button');

    $page->assertSeeIn('@reasoning', 'First I check the catalog.')
        ->assertMissing('@message-assistant-loading')
        ->assertNoJavaScriptErrors();
})->with([
    'tool' => [['type' => 'tool-input-available', 'toolCallId' => 'lookup', 'toolName' => 'Lookup', 'input' => []]],
    'notice' => [['type' => 'data-ai-nexus-notice', 'data' => ['message' => 'Retrying.']]],
    'error' => [['type' => 'error', 'errorText' => 'Provider failed.']],
    'provider tool' => [[
        'type' => 'data-provider-tool',
        'data' => ['item_id' => 'lookup', 'type' => 'web_search', 'status' => 'completed', 'data' => []],
    ]],
]);

it('shows per-message and conversation token counts', function (): void {
    fakeChatAgent(SupportAgent::class, [
        new TextResponse(
            'Counted.',
            new TextUsage(inputTokens: 142, outputTokens: 89),
            new Meta('openai', 'gpt-5.6-luna'),
        ),
    ]);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');

    $page->type('@composer-input', 'Count my tokens')->click('Send');

    $page->assertSeeIn('@message-meta', 'Prompt: 142')
        ->assertSeeIn('@message-meta', 'Completion: 89')
        ->assertSeeIn('@message-meta', 'Total: 231')
        ->assertSeeIn('@conversation-tokens', 'Total 231');
});

it('restores the thread on a refresh', function (): void {
    fakeChatAgent(SupportAgent::class, ['Thirty days.']);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');
    $page->type('@composer-input', 'What is your return policy?')->click('Send');
    $page->assertSeeIn('@message-assistant', 'Thirty days.');

    // The id the send announced is what the URL now carries; a fresh visit to
    // that URL is exactly what a refresh does.
    $conversationId = chatTables()[0]->find()->firstOrFail()->id;

    visit("/ai_nexus/playground/test-app.ai.agents.support-agent?c={$conversationId}")
        ->assertSeeIn('@message-user', 'What is your return policy?')
        ->assertSeeIn('@message-assistant', 'Thirty days.')
        ->assertNoJavaScriptErrors();
});

it('marks a stateless agent and leaves a conversational one unmarked', function (): void {
    visit('/ai_nexus/playground/test-app.ai.agents.weather-agent')
        ->assertPresent('@stateless-notice')
        ->assertSeeIn('@stateless-notice', 'each message is sent independently');

    visit('/ai_nexus/playground/test-app.ai.agents.support-agent')
        ->assertMissing('@stateless-notice');
});

it('keeps answering a stateless agent across turns', function (): void {
    fakeChatAgent(WeatherAgent::class, ['Sunny in Tbilisi.', 'Sunny again tomorrow.']);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.weather-agent');

    $page->type('@composer-input', 'Weather in Tbilisi?')->click('Send');
    $page->assertSeeIn('@chat-thread', 'Sunny in Tbilisi.');

    $page->type('@composer-input', 'And tomorrow?')->click('Send');
    $page->assertSeeIn('@chat-thread', 'Sunny again tomorrow.');
});

it('explains a failure inline and keeps the playground usable', function (): void {
    fakeChatAgent(FlakyToolAgent::class, [
        new ToolCall(id: 'call_1', name: 'BrokenLedgerTool', arguments: ['entry' => '42']),
    ]);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.flaky-tool-agent');

    $page->type('@composer-input', 'Look up entry 42')->click('Send');

    $page->assertPresent('@error-card')
        ->assertSeeIn('@error-card', 'Ledger service unavailable')
        ->assertSeeIn('@error-card', 'RuntimeException')
        // The trace is available but out of the way until asked for.
        ->assertMissing('@stack-trace')
        ->assertPresent('@chat-composer');

    // The Collapsible prepends a chevron to its label, so "Stack trace"
    // never matches exactly — target the trigger explicitly (text clicks
    // hang instead of failing when nothing matches exactly).
    $page->assertPresent('[data-testid="error-card"] .collapsible-trigger');
    $page->click('[data-testid="error-card"] .collapsible-trigger');

    $page->assertPresent('@stack-trace')
        ->assertSeeIn('@stack-trace', 'BrokenLedgerTool')
        ->assertNoJavaScriptErrors();
});

it('shows a tool call as a card and expands it', function (): void {
    fakeChatAgent(SupportAgent::class, [
        new ToolCall(id: 'call_1', name: 'SearchProductsTool', arguments: ['query' => 'hoodie']),
        'Found three matches.',
    ]);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');

    $page->type('@composer-input', 'Find me a hoodie')->click('Send');

    $page->assertPresent('@tool-card')
        ->assertSeeIn('@tool-card', 'SearchProductsTool')
        // Collapsed by default so a chain of calls stays readable.
        ->assertMissing('@tool-arguments');

    $page->click('[aria-label="SearchProductsTool tool call"]');

    $page->assertSeeIn('@tool-arguments', 'hoodie')
        ->assertSeeIn('@tool-result', 'Sony WH-1000')
        ->assertNoJavaScriptErrors();
});

it('renders a tool card and its answer in the order they happened', function (): void {
    fakeChatAgent(SupportAgent::class, [
        new ToolCall(id: 'call_1', name: 'SearchProductsTool', arguments: ['query' => 'hoodie']),
        'Found three matches.',
    ]);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');

    $page->type('@composer-input', 'Find me a hoodie')->click('Send');
    $page->assertSeeIn('@message-assistant', 'Found three matches.');

    // The call happened before the answer, so its card sits above it.
    $order = $page->script(
        "Array.from(document.querySelectorAll('[data-testid=tool-card],[data-testid=message-assistant]'))
            .map(el => el.dataset.testid)",
    );

    expect($order)->toBe(['tool-card', 'message-assistant']);
});

it('says so when the runtime cannot stream', function (): void {
    // The driver runs CakePHP in-process on the CLI SAPI, so this harness is
    // itself a non-streaming runtime. That makes the notice always visible
    // here, which is exactly the state a developer on such a runtime sees,
    // and it is the truth: nothing on this page will arrive incrementally.
    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');

    $page->assertPresent('@streaming-notice')
        ->assertSeeIn('@streaming-notice', 'replies appear all at once')
        ->assertNoJavaScriptErrors();

    $page->click('System')->click('Dark');

    // Dark mode is a separate stylesheet toggled by its media attribute
    // (see resources/frontend/js/composables/useTheme.js), not a class.
    expect($page->script("document.querySelector('style[data-scheme=\"dark\"]').media"))->not->toBe('max-width: 1px');

    $page->assertPresent('@streaming-notice')->assertNoJavaScriptErrors();
});

it('resolves a slow tool without leaving the card behind', function (): void {
    // The `pending` window itself is not assertable here: the browser driver
    // runs CakePHP in-process and collects the whole response with
    // `ob_start()` / `ob_get_clean()`, so every SSE part reaches the page in one
    // chunk no matter how long the tool takes. What this does cover is that a
    // multi-second tool still lands as a resolved card with its answer below.
    fakeChatAgent(SlowToolAgent::class, [
        new ToolCall(id: 'call_1', name: 'SlowTool', arguments: ['seconds' => 1]),
        'Forty-two rows.',
    ]);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.slow-tool-agent');

    $page->type('@composer-input', 'Query the analytics service')->click('Send');

    $page->assertPresent('[data-tool-status=success]')
        ->assertSeeIn('@tool-card', 'SlowTool')
        ->assertSeeIn('@message-assistant', 'Forty-two rows.')
        ->assertNoJavaScriptErrors();
});

it('marks a failed tool on the card and explains it below', function (): void {
    fakeChatAgent(FlakyToolAgent::class, [
        new ToolCall(id: 'call_1', name: 'BrokenLedgerTool', arguments: ['entry' => '42']),
    ]);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.flaky-tool-agent');

    $page->type('@composer-input', 'Look up entry 42')->click('Send');

    // The card says which tool; the error card says what went wrong.
    $page->assertSeeIn('@tool-status', 'error')
        ->assertSeeIn('@error-card', 'Ledger service unavailable')
        ->assertNoJavaScriptErrors();
});

it('restores tool cards on a refresh', function (): void {
    fakeChatAgent(SupportAgent::class, [
        new ToolCall(id: 'call_1', name: 'SearchProductsTool', arguments: ['query' => 'hoodie']),
        'Found three matches.',
    ]);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');
    $page->type('@composer-input', 'Find me a hoodie')->click('Send');
    $page->assertPresent('@tool-card');

    $conversationId = chatTables()[0]->find()->firstOrFail()->id;

    $reopened = visit("/ai_nexus/playground/test-app.ai.agents.support-agent?c={$conversationId}");

    $reopened->assertSeeIn('@tool-card', 'SearchProductsTool')
        ->assertSeeIn('@message-assistant', 'Found three matches.');

    $reopened->click('[aria-label="SearchProductsTool tool call"]');

    $reopened->assertSeeIn('@tool-result', 'Sony WH-1000')->assertNoJavaScriptErrors();
});

it('clears the thread when you switch agents', function (): void {
    fakeChatAgent(SupportAgent::class, ['Returns are accepted within thirty days.']);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');

    $page->type('@composer-input', 'What is your return policy?')->click('Send');
    $page->assertSeeIn('@message-assistant', 'Returns are accepted within thirty days.');

    // One agent's conversation must never appear under another agent's name.
    // Targeted by href: the sidebar uppercases its labels in CSS, so the
    // rendered text and the DOM text disagree.
    $page->click('a[href$="test-app.ai.agents.weather-agent"]');

    $page->assertPresent('@chat-empty')
        ->assertMissing('@message-assistant')
        ->assertMissing('@conversation-tokens')
        ->assertNoJavaScriptErrors();
});

it('keeps the live thread when the conversation id lands in the url', function (): void {
    // The server announces the id mid-stream and the page puts it in the URL.
    // If that were treated as "load this conversation", the fetch would replace
    // the in-flight thread with whatever happened to be stored at that moment.
    fakeChatAgent(SupportAgent::class, [
        new ToolCall(id: 'call_1', name: 'SearchProductsTool', arguments: ['query' => 'hoodie']),
        'Found three matches.',
    ]);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');

    $page->type('@composer-input', 'Find me a hoodie')->click('Send');

    $page->assertSeeIn('@message-assistant', 'Found three matches.')
        ->assertPresent('@tool-card')
        ->assertPresent('@message-meta')
        ->assertNoJavaScriptErrors();
});

it('leaves the composer one line tall on an empty playground', function (): void {
    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');

    $page->assertPresent('@chat-empty');

    $height = $page->script(
        "document.querySelector('[data-testid=composer-input]').getBoundingClientRect().height",
    );

    expect($height)->toBeLessThan(60);
});

/*
| Attach a file the way the browser would.
|
| Playwright refuses local paths when the client is not local, so the file is
| constructed in the page and handed to the same input the picker drives — or,
| with `drop: true`, dropped onto the composer.
|
| These tests stop at the composer on purpose. Everything past the chip —
| upload, storage, rehydration into the next turn, serving the file back — is
| covered by the attachment feature tests against a real multipart request.
|
| @param object $page Browser page.
| @param bool $drop Drop onto the composer instead of using the picker.
| @return void
*/
function attachFile(object $page, bool $drop = false): void
{
    $mode = $drop ? 'true' : 'false';

    $page->script(<<<JS
        (() => {
            const file = new File([new Uint8Array([137, 80, 78, 71])], 'sky.png', { type: 'image/png' });
            const transfer = new DataTransfer();
            transfer.items.add(file);

            if ({$mode}) {
                const composer = document.querySelector('[data-testid=chat-composer]');
                composer.dispatchEvent(new DragEvent('dragenter', { bubbles: true, dataTransfer: transfer }));
                composer.dispatchEvent(new DragEvent('drop', { bubbles: true, dataTransfer: transfer }));

                return;
            }

            const input = document.querySelector('input[type=file]');
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        })()
    JS);
}

it('attaches a file from the picker and lets you remove it', function (): void {
    $page = visit('/ai_nexus/playground/test-app.ai.agents.vision-agent');

    attachFile($page);

    $page->assertSeeIn('@file-chip', 'sky.png');

    $page->click('[aria-label="Remove sky.png"]');

    $page->assertMissing('@file-chip')->assertNoJavaScriptErrors();
});

it('accepts a file dropped onto the composer', function (): void {
    $page = visit('/ai_nexus/playground/test-app.ai.agents.vision-agent');

    attachFile($page, drop: true);

    $page->assertSeeIn('@file-chip', 'sky.png')->assertNoJavaScriptErrors();
});

it('offers the agent model plus its provider tiers', function (): void {
    Configure::write('Ai.providers.openai.models.text.cheapest', 'test-cheapest-model');
    Configure::write('Ai.providers.openai.models.text.smartest', 'test-smartest-model');

    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');

    $page->assertSeeIn('@model-selector', 'gpt-5.6-luna');

    $page->click('[aria-label="Model: gpt-5.6-luna"]');

    $page->assertSee('agent default')
        ->assertSee('cheapest')
        ->assertSee('smartest')
        ->assertNoJavaScriptErrors();
});

it('names the model on a message that overrode it', function (): void {
    fakeChatAgent(SupportAgent::class, ['Answered elsewhere.']);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');

    $page->click('[aria-label="Model: gpt-5.6-luna"]');
    $page->click('smartest');

    $page->type('@composer-input', 'Try the smart one')->click('Send');

    // A replayed conversation must never let an override read as the agent's
    // own configuration.
    $page->assertSeeIn('@message-meta', 'on ')->assertNoJavaScriptErrors();
});

it('renders a structured agent as a json card', function (): void {
    fakeChatAgent(ExtractorAgent::class, [
        ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
    ]);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.extractor-agent');

    $page->type('@composer-input', 'Ada Lovelace, ada@example.com')->click('Send');

    $page->assertPresent('@structured-card')
        ->assertSeeIn('@structured-output', 'Ada Lovelace')
        ->assertNoJavaScriptErrors();
});

it('starts a fresh thread from the conversation menu', function (): void {
    fakeChatAgent(SupportAgent::class, ['An answer.']);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');

    $page->type('@composer-input', 'A question')->click('Send');
    $page->assertSeeIn('@message-assistant', 'An answer.');

    $page->click('[aria-label="Conversation actions"]');
    $page->click('New conversation');

    $page->assertPresent('@chat-empty')
        ->assertMissing('@message-assistant')
        ->assertNoJavaScriptErrors();
});

it('clears a conversation and its stored rows', function (): void {
    fakeChatAgent(SupportAgent::class, ['An answer.']);

    $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');

    $page->type('@composer-input', 'A question')->click('Send');
    $page->assertSeeIn('@message-assistant', 'An answer.');

    $page->click('[aria-label="Conversation actions"]');
    $page->click('Clear conversation');

    $page->assertPresent('@chat-empty');

    expect(chatTables()[0]->find()->count())->toBe(0);
});

it('renders the conversation in both themes', function (): void {
    // A closure rather than a list: this test sends once per theme, and a list
    // would run out after the first.
    fakeChatAgent(SupportAgent::class, fn(): string => 'An answer in any theme.');

    foreach (['Light', 'Dark'] as $theme) {
        $page = visit('/ai_nexus/playground/test-app.ai.agents.support-agent');

        // The switcher's trigger is labelled with the current theme; a fresh
        // context always starts on System.
        $page->click('System')->click($theme);

        $page->type('@composer-input', 'A question')->click('Send');

        $page->assertSeeIn('@message-assistant', 'An answer in any theme.')
            ->assertNoJavaScriptErrors();
    }
});
