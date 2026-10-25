export function emptyAssistant(turnId, id) {
    return {
        kind: 'assistant',
        id,
        turnId,
        text: '',
        pending: false,
        reasoning: '',
        reasoningStreaming: false,
        streaming: true,
        usage: null,
        durationMs: null,
        meta: null,
        structured: null,
    };
}

export function pendingAssistant(turnId) {
    return { ...emptyAssistant(turnId, pendingAssistantId(turnId)), pending: true };
}

export function pendingAssistantId(turnId) {
    return `${turnId}:pending`;
}

export function dropPendingAssistant(entries, turnId) {
    return entries.filter(
        (entry) => !(entry.kind === 'assistant' && entry.turnId === turnId && entry.pending),
    );
}

export function lastAssistantId(entries, turnId) {
    for (let index = entries.length - 1; index >= 0; index -= 1) {
        const entry = entries[index];

        if (entry.kind === 'assistant' && entry.turnId === turnId) {
            return entry.id;
        }
    }

    return null;
}

export function updateLastAssistant(entries, turnId, update) {
    const target = lastAssistantId(entries, turnId);

    if (target === null) {
        return entries;
    }

    return entries.map((entry) =>
        entry.kind === 'assistant' && entry.id === target ? update(entry) : entry,
    );
}

export function openAssistant(entries, turnId, id, overrides = {}) {
    const pendingId = pendingAssistantId(turnId);

    return entries.some((entry) => entry.kind === 'assistant' && entry.id === pendingId)
        ? entries.map((entry) =>
            entry.kind === 'assistant' && entry.id === pendingId
                ? { ...entry, id, pending: false, ...overrides }
                : entry,
        )
        : [...entries, { ...emptyAssistant(turnId, id), ...overrides }];
}

export function appendText(entries, turnId, blockId, delta) {
    const id = `${turnId}:${blockId}`;
    const pendingId = pendingAssistantId(turnId);
    const existing = entries.some((entry) => entry.kind === 'assistant' && entry.id === id);

    if (existing) {
        return entries.map((entry) =>
            entry.kind === 'assistant' && entry.id === id
                ? { ...entry, text: entry.text + delta }
                : entry,
        );
    }

    if (entries.some((entry) => entry.kind === 'assistant' && entry.id === pendingId)) {
        return entries.map((entry) =>
            entry.kind === 'assistant' && entry.id === pendingId
                ? { ...entry, id, pending: false, text: delta }
                : entry,
        );
    }

    return [...entries, { ...emptyAssistant(turnId, id), text: delta }];
}

export function openReasoning(entries, turnId) {
    if (lastAssistantId(entries, turnId) !== null) {
        return updateLastAssistant(entries, turnId, (entry) => ({
            ...entry,
            pending: false,
            reasoningStreaming: true,
        }));
    }

    return openAssistant(entries, turnId, `${turnId}:reasoning`, { reasoningStreaming: true });
}

export function settleTurn(entries, turnId, messageId, usage, durationMs) {
    const target = lastAssistantId(entries, turnId);

    return entries.map((entry) => {
        if (entry.kind !== 'assistant' || entry.turnId !== turnId) {
            return entry;
        }

        return entry.id === target
            ? {
                ...entry,
                id: messageId ?? entry.id,
                pending: false,
                streaming: false,
                reasoningStreaming: false,
                usage,
                durationMs,
            }
            : { ...entry, pending: false, streaming: false, reasoningStreaming: false };
    });
}

export function stopStreaming(entries, turnId) {
    return entries
        .map((entry) =>
            entry.kind === 'assistant' && entry.turnId === turnId
                ? { ...entry, pending: false, streaming: false, reasoningStreaming: false }
                : entry,
        )
        .filter(
            (entry) =>
                entry.kind !== 'assistant'
                || entry.text !== ''
                || entry.reasoning !== ''
                || entry.structured !== null,
        );
}

export function updateTool(entries, toolCallId, update) {
    return entries.map((entry) =>
        entry.kind === 'tool' && entry.id === toolCallId ? update(entry) : entry,
    );
}

export function failPendingTools(entries, message) {
    return entries.map((entry) =>
        entry.kind === 'tool' && entry.status === 'pending'
            ? { ...entry, status: 'error', error: entry.error ?? message }
            : entry,
    );
}

const PROVIDER_SUCCESS = ['completed', 'complete', 'result_received', 'done', 'succeeded'];
const PROVIDER_FAILED = ['failed', 'error', 'errored', 'incomplete', 'cancelled', 'canceled'];

export function normalizeProviderStatus(status) {
    const value = String(status).toLowerCase();

    if (PROVIDER_SUCCESS.includes(value)) {
        return 'success';
    }

    if (PROVIDER_FAILED.includes(value)) {
        return 'error';
    }

    return 'pending';
}

export function applyProviderTool(entries, event, provider) {
    const status = normalizeProviderStatus(event.status);
    const known = entries.some((entry) => entry.kind === 'tool' && entry.id === event.item_id);

    if (known) {
        return updateTool(entries, event.item_id, (entry) => ({
            ...entry,
            status,
            providerStatus: event.status,
            result: status === 'pending' ? entry.result : event.data,
        }));
    }

    return [
        ...entries,
        {
            kind: 'tool',
            id: event.item_id,
            type: 'provider_tool',
            name: typeof event.data?.name === 'string' ? event.data.name : event.type,
            provider,
            arguments: event.data,
            result: status === 'pending' ? null : event.data,
            status,
            providerStatus: event.status,
            error: null,
            durationMs: null,
        },
    ];
}

export function toToolEntry(invocation, messages) {
    return {
        kind: 'tool',
        id: invocation.id,
        type: invocation.type,
        name: invocation.name,
        provider:
            invocation.type === 'provider_tool'
                ? (messages.find((message) => message.id === invocation.message_id)?.meta?.provider ?? null)
                : null,
        arguments: invocation.arguments,
        result: invocation.result,
        status: invocation.status,
        providerStatus: invocation.provider_status,
        error: invocation.error,
        durationMs: invocation.duration_ms,
    };
}

export function toEntry(message) {
    if (message.role === 'user') {
        return {
            kind: 'user',
            id: message.id,
            content: message.content ?? '',
            attachments: message.attachments ?? [],
        };
    }

    if (message.role === 'error') {
        return {
            kind: 'error',
            id: message.id,
            message: message.content ?? 'The agent failed.',
            exceptionClass: message.metadata?.exception_class ?? null,
            stackTrace: message.metadata?.stack_trace ?? null,
            responseStatus: message.metadata?.response_status ?? null,
            responseBody: message.metadata?.response_body ?? null,
            recoverable: Boolean(message.metadata?.recoverable),
        };
    }

    return {
        kind: 'assistant',
        id: message.id,
        turnId: message.id,
        text: message.meta?.structured ? '' : (message.content ?? ''),
        reasoning: message.meta?.reasoning ?? '',
        pending: false,
        reasoningStreaming: false,
        streaming: false,
        usage: message.usage,
        durationMs: message.duration_ms,
        meta: message.meta,
        structured: message.meta?.structured ?? null,
    };
}

export function replay(conversation) {
    return [
        ...conversation.messages.map(toEntry),
        ...conversation.tool_invocations.map((invocation) => toToolEntry(invocation, conversation.messages)),
    ].sort((a, b) => (a.id < b.id ? -1 : a.id > b.id ? 1 : 0));
}

export function totalsOf(entries) {
    return entries.reduce(
        (totals, entry) => {
            if (entry.kind !== 'assistant' || !entry.usage) {
                return totals;
            }

            return {
                prompt: totals.prompt + (entry.usage.input_tokens ?? 0),
                completion: totals.completion + (entry.usage.output_tokens ?? 0),
            };
        },
        { prompt: 0, completion: 0 },
    );
}
