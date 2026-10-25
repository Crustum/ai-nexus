import { describe, expect, it } from 'vitest';
import { dispatch, toChatError } from '../composables/useChatStream';

describe('dispatch', () => {
    it('announces the conversation on data-ai-nexus-start', () => {
        let seen = null;

        dispatch(
            { type: 'data-ai-nexus-start', data: { conversationId: 'c1', userMessageId: 'm1' } },
            { onConversation: (id, messageId) => { seen = [id, messageId]; } },
        );

        expect(seen).toEqual(['c1', 'm1']);
    });

    it('routes text deltas with block id', () => {
        let seen = null;

        dispatch({ type: 'text-delta', id: 'b1', delta: 'hi' }, { onTextDelta: (id, delta) => { seen = [id, delta]; } });

        expect(seen).toEqual(['b1', 'hi']);
    });

    it('routes tool input and output', () => {
        const calls = [];

        dispatch(
            { type: 'tool-input-available', toolCallId: 'call_1', toolName: 'Search', input: { q: 'x' } },
            { onToolInput: (id, name, input) => calls.push(['in', id, name, input]) },
        );
        dispatch(
            { type: 'tool-output-available', toolCallId: 'call_1', output: 'ok' },
            { onToolOutput: (id, output) => calls.push(['out', id, output]) },
        );

        expect(calls).toEqual([
            ['in', 'call_1', 'Search', { q: 'x' }],
            ['out', 'call_1', 'ok'],
        ]);
    });

    it('finishes with usage on data-ai-nexus-end', () => {
        let seen = null;
        const usage = { input_tokens: 1, output_tokens: 2 };

        dispatch(
            { type: 'data-ai-nexus-end', data: { assistantMessageId: 'a1', usage, durationMs: 5 } },
            { onFinish: (id, u, ms) => { seen = [id, u, ms]; } },
        );

        expect(seen).toEqual(['a1', usage, 5]);
    });

    it('ignores stream lifecycle markers', () => {
        let seen = null;
        const handlers = { onPart: (part) => { seen = part; } };

        dispatch({ type: 'start' }, handlers);
        dispatch({ type: 'text-start' }, handlers);
        dispatch({ type: 'text-end' }, handlers);

        expect(seen).toBeNull();
    });

    it('passes unknown parts through', () => {
        let seen = null;

        dispatch({ type: 'some-future-part' }, { onPart: (part) => { seen = part; } });

        expect(seen).toEqual({ type: 'some-future-part' });
    });

    it('finishes on bare finish with usage', () => {
        let seen = null;
        const usage = { input_tokens: 3, completion_tokens: 4 };

        dispatch(
            { type: 'finish', usage },
            { onFinish: (id, u, ms) => { seen = [id, u, ms]; } },
        );

        expect(seen).toEqual([null, usage, null]);
    });

    it('builds chat errors', () => {
        expect(toChatError({ type: 'error', errorText: 'bad', data: {} }).message).toBe('bad');
    });
});

