import { spaBasePath } from '../utils/api';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export function toFormData(body) {
    const form = new FormData();

    form.append('message', body.message);

    if (body.conversation_id) {
        form.append('conversation_id', body.conversation_id);
    }

    if (body.model) {
        form.append('model', body.model);
    }

    for (const file of body.files ?? []) {
        form.append('attachments[]', file);
    }

    return form;
}

export function dispatch(part, handlers) {
    switch (part.type) {
        case 'start':
        case 'text-start':
        case 'text-end':
            break;
        case 'finish': {
            const usage = part.usage ?? part.data?.usage ?? null;
            const durationMs = part.durationMs ?? part.data?.durationMs ?? null;

            handlers.onFinish?.(null, usage, durationMs);
            break;
        }
        case 'source-url':
            if (handlers.onSource) {
                handlers.onSource(part.data ?? part);
            } else {
                handlers.onPart?.(part);
            }

            break;
        case 'tool-output-denied':
            if (handlers.onToolDenied) {
                handlers.onToolDenied(part.toolCallId ?? '', part.data ?? null);
            } else {
                handlers.onToolError?.(part.toolCallId ?? '', 'The tool run was denied.');
            }

            break;
        case 'tool-approval-request':
            if (handlers.onApproval) {
                handlers.onApproval(part.data ?? part);
            } else {
                handlers.onPart?.(part);
            }

            break;
        case 'data-ai-nexus-start':
        case 'data-synapse-start':
            handlers.onConversation?.(part.data?.conversationId, part.data?.userMessageId);
            break;
        case 'text-delta':
            handlers.onTextDelta?.(part.id ?? 'text', part.delta ?? '');
            break;
        case 'error':
            handlers.onError?.(toChatError(part));
            break;
        case 'data-ai-nexus-notice':
        case 'data-synapse-notice':
            handlers.onNotice?.(part.data?.message);
            break;
        case 'data-ai-nexus-provider':
        case 'data-synapse-provider':
            handlers.onProvider?.(part.data?.provider);
            break;
        case 'data-structured-output':
            handlers.onStructured?.(part.data ?? {});
            break;
        case 'tool-input-available':
            handlers.onToolInput?.(part.toolCallId ?? '', part.toolName ?? 'tool', part.input);
            break;
        case 'tool-output-available':
            handlers.onToolOutput?.(part.toolCallId ?? '', part.output);
            break;
        case 'tool-output-error':
            handlers.onToolError?.(part.toolCallId ?? '', part.errorText ?? 'The tool failed.');
            break;
        case 'data-provider-tool':
            handlers.onProviderTool?.(part.data);
            break;
        case 'reasoning-start':
            handlers.onReasoningStart?.();
            break;
        case 'reasoning-delta':
            handlers.onReasoningDelta?.(part.delta ?? '');
            break;
        case 'reasoning-end':
            handlers.onReasoningEnd?.();
            break;
        case 'data-ai-nexus-end':
        case 'data-synapse-end':
            handlers.onFinish?.(
                (part.data?.assistantMessageId ?? null),
                (part.data?.usage ?? null),
                (part.data?.durationMs ?? null),
            );
            break;
        default:
            handlers.onPart?.(part);
            break;
    }
}

export function toChatError(part) {
    return {
        messageId: part.data?.messageId ?? '',
        message: part.errorText ?? 'The agent failed.',
        exceptionClass: part.data?.exceptionClass ?? null,
        stackTrace: part.data?.stackTrace ?? null,
        responseStatus: part.data?.responseStatus ?? null,
        responseBody: part.data?.responseBody ?? null,
        recoverable: Boolean(part.data?.recoverable),
    };
}

export async function streamChat(slug, body, handlers, signal) {
    const hasFiles = (body.files?.length ?? 0) > 0;
    const base = spaBasePath().replace(/\/$/, '');

    const response = await fetch(`${base}/api/chat/${encodeURIComponent(slug)}/send`, {
        method: 'POST',
        signal,
        headers: {
            ...(hasFiles ? {} : { 'Content-Type': 'application/json' }),
            Accept: 'text/event-stream',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: hasFiles ? toFormData(body) : JSON.stringify(body),
    });

    if (!response.ok || !response.body) {
        throw new Error(`AiNexus chat request failed: ${response.status}`);
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    for (;;) {
        const { done, value } = await reader.read();

        if (done) {
            break;
        }

        buffer += decoder.decode(value, { stream: true });

        const events = buffer.split('\n\n');
        buffer = events.pop() ?? '';

        for (const event of events) {
            const payload = event.replace(/^data: /gm, '').trim();

            if (payload === '' || payload === '[DONE]' || payload.startsWith(':')) {
                continue;
            }

            let part = null;

            try {
                part = JSON.parse(payload);
            } catch {
                continue;
            }

            dispatch(part, handlers);
        }
    }
}

