<?php
declare(strict_types=1);

it('serves the dashboard shell when authorized', function (): void {
    test()->get('/ai_nexus');
    test()->assertResponseOk();
    test()->assertResponseContains('id="ai-nexus"');
    test()->assertResponseContains('window.AiNexus');
    test()->assertResponseContains('/ai_nexus-frontend/app.js');
    test()->assertResponseContains('/ai_nexus-frontend/styles.css');
});

it('serves the shell on deep links for client-side routing', function (): void {
    test()->get('/ai_nexus/history/some-conversation');
    test()->assertResponseOk();
    test()->assertResponseContains('window.AiNexus');
});

it('forbids access when host middleware denies', function (): void {
    denyDashboard();

    test()->get('/ai_nexus');
    test()->assertResponseCode(403);
});
