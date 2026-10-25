import { describe, expect, it } from 'vitest';
import {
    appendText,
    applyProviderTool,
    failPendingTools,
    normalizeProviderStatus,
    replay,
    totalsOf,
} from '../utils/chat';

describe('appendText', () => {
    it('opens a new assistant entry on first delta', () => {
        const entries = appendText([], 't1', 'b1', 'hi');

        expect(entries).toHaveLength(1);
        expect(entries[0].text).toBe('hi');
    });

    it('appends to the same block', () => {
        const first = appendText([], 't1', 'b1', 'hi');
        const second = appendText(first, 't1', 'b1', ' there');

        expect(second).toHaveLength(1);
        expect(second[0].text).toBe('hi there');
    });
});

describe('provider tools', () => {
    it('normalizes statuses', () => {
        expect(normalizeProviderStatus('completed')).toBe('success');
        expect(normalizeProviderStatus('failed')).toBe('error');
        expect(normalizeProviderStatus('running')).toBe('pending');
    });

    it('opens a card on first event', () => {
        const entries = applyProviderTool([], { item_id: 'x', type: 'web_search', status: 'running', data: {} }, 'openai');

        expect(entries[0].status).toBe('pending');
        expect(entries[0].provider).toBe('openai');
    });

    it('fails pending tools at end of stream', () => {
        const entries = failPendingTools(
            [{ kind: 'tool', id: 'x', status: 'pending', error: null }],
            'stopped',
        );

        expect(entries[0].status).toBe('error');
    });
});

describe('replay', () => {
    it('orders messages and tools by id', () => {
        const entries = replay({
            messages: [{ id: 'b', role: 'user', content: 'hi' }],
            tool_invocations: [{
                id: 'a', message_id: 'b', type: 'tool', name: 'T', arguments: {}, result: null,
                status: 'success', provider_status: null, error: null, duration_ms: null,
            }],
        });

        expect(entries.map((entry) => entry.id)).toEqual(['a', 'b']);
    });
});

describe('totalsOf', () => {
    it('sums usage across turns', () => {
        expect(
            totalsOf([{ kind: 'assistant', usage: { input_tokens: 2, output_tokens: 3 } }]),
        ).toEqual({ prompt: 2, completion: 3 });
    });
});
