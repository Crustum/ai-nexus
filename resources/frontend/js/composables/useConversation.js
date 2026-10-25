import { onBeforeUnmount, ref, watch } from 'vue';
import { deleteConversation, getConversation } from '../services/api';
import { streamChat } from './useChatStream';
import {
    appendText,
    applyProviderTool,
    dropPendingAssistant,
    failPendingTools,
    openAssistant,
    openReasoning,
    pendingAssistant,
    replay,
    settleTurn,
    stopStreaming,
    totalsOf,
    updateLastAssistant,
    updateTool,
} from '../utils/chat';

export function useConversation(slugRef, conversationIdRef) {
    const entries = ref([]);
    const sending = ref(false);
    const loading = ref(false);
    const totals = ref({ prompt: 0, completion: 0 });

    let abort = null;
    let loadAbort = null;
    let loaded = null;
    let provider = null;
    let loadToken = 0;

    function syncTotals() {
        totals.value = totalsOf(entries.value);
    }

    function reset() {
        abort?.abort();
        entries.value = [];
        loaded = null;
        provider = null;
        syncTotals();
    }

    async function clear() {
        abort?.abort();

        if (conversationIdRef.value) {
            await deleteConversation(conversationIdRef.value).catch(() => {});
        }

        entries.value = [];
        loaded = null;
        syncTotals();
    }

    async function loadThread(id) {
        loadAbort?.abort();

        if (!id) {
            entries.value = [];
            loaded = null;
            syncTotals();

            return;
        }

        if (loaded === id) {
            return;
        }

        const token = ++loadToken;
        const controller = new AbortController();

        loadAbort = controller;
        loading.value = true;

        try {
            const conversation = await getConversation(id, controller.signal);

            if (token !== loadToken || controller.signal.aborted) {
                return;
            }

            loaded = conversation.id;
            entries.value = replay(conversation);
            syncTotals();
        } catch {
        } finally {
            if (token === loadToken) {
                loading.value = false;
            }
        }
    }

    async function send(message, onConversation, options = {}) {
        const slug = slugRef.value;
        const files = options.files ?? [];
        const conversationId = conversationIdRef.value;

        if (!slug || sending.value || (message.trim() === '' && files.length === 0)) {
            return;
        }

        const controller = new AbortController();

        abort = controller;
        sending.value = true;

        const turnId = `turn-${Date.now()}`;

        entries.value = [
            ...entries.value,
            {
                kind: 'user',
                id: `${turnId}-user`,
                content: message,
                attachments: files.map((file) => ({ type: file.type, name: file.name, url: URL.createObjectURL(file) })),
            },
            pendingAssistant(turnId),
        ];
        syncTotals();

        try {
            await streamChat(
                slug,
                { message, conversation_id: conversationId, model: options.model, files },
                {
                    onConversation: (id) => {
                        loaded = id;
                        onConversation(id);
                    },
                    onTextDelta: (blockId, delta) => {
                        entries.value = appendText(entries.value, turnId, blockId, delta);
                        syncTotals();
                    },
                    onStructured: (data) => {
                        const opened = openAssistant(entries.value, turnId, `${turnId}:structured`);

                        entries.value = updateLastAssistant(opened, turnId, (entry) => ({ ...entry, structured: data }));
                        syncTotals();
                    },
                    onToolInput: (toolCallId, name, input) => {
                        entries.value = [
                            ...dropPendingAssistant(entries.value, turnId),
                            {
                                kind: 'tool', id: toolCallId, type: 'tool', name, provider: null,
                                arguments: input, result: null, status: 'pending',
                                providerStatus: null, error: null, durationMs: null,
                            },
                        ];
                    },
                    onToolOutput: (toolCallId, output) => {
                        entries.value = updateTool(dropPendingAssistant(entries.value, turnId), toolCallId, (entry) => ({
                            ...entry, status: 'success', result: output,
                        }));
                    },
                    onToolError: (toolCallId, errorText) => {
                        entries.value = updateTool(dropPendingAssistant(entries.value, turnId), toolCallId, (entry) => ({
                            ...entry, status: 'error', error: errorText,
                        }));
                    },
                    onProvider: (name) => {
                        provider = name;
                    },
                    onProviderTool: (event) => {
                        entries.value = applyProviderTool(dropPendingAssistant(entries.value, turnId), event, provider);
                    },
                    onReasoningStart: () => {
                        entries.value = openReasoning(entries.value, turnId);
                    },
                    onReasoningDelta: (delta) => {
                        entries.value = updateLastAssistant(openReasoning(entries.value, turnId), turnId, (entry) => ({
                            ...entry, reasoning: entry.reasoning + delta,
                        }));
                    },
                    onReasoningEnd: () => {
                        entries.value = updateLastAssistant(entries.value, turnId, (entry) => ({
                            ...entry, reasoningStreaming: false,
                        }));
                    },
                    onNotice: (notice) => {
                        entries.value = [
                            ...dropPendingAssistant(entries.value, turnId),
                            { kind: 'notice', id: `${turnId}-notice-${entries.value.length}`, message: notice },
                        ];
                    },
                    onSource: () => {},
                    onApproval: () => {},
                    onPart: () => {},
                    onError: (chatError) => {
                        entries.value = [
                            ...failPendingTools(dropPendingAssistant(entries.value, turnId), chatError.message),
                            {
                                kind: 'error', id: chatError.messageId || `${turnId}-error-${entries.value.length}`,
                                message: chatError.message, exceptionClass: chatError.exceptionClass,
                                stackTrace: chatError.stackTrace, responseStatus: chatError.responseStatus,
                                responseBody: chatError.responseBody, recoverable: chatError.recoverable,
                            },
                        ];
                    },
                    onFinish: (assistantMessageId, usage, durationMs) => {
                        entries.value = settleTurn(entries.value, turnId, assistantMessageId, usage, durationMs);
                        syncTotals();
                    },
                },
                controller.signal,
            );
        } catch (cause) {
            if (!controller.signal.aborted) {
                entries.value = [
                    ...dropPendingAssistant(entries.value, turnId),
                    {
                        kind: 'error', id: `${turnId}-transport`,
                        message: cause instanceof Error ? cause.message : 'The request failed.',
                        exceptionClass: null, stackTrace: null, responseStatus: null,
                        responseBody: null, recoverable: false,
                    },
                ];
            }
        } finally {
            entries.value = stopStreaming(entries.value, turnId);
            entries.value = failPendingTools(entries.value, 'The run ended before this tool returned.');
            syncTotals();
            sending.value = false;
            abort = null;
        }
    }

    watch(() => slugRef.value, () => {
        abort?.abort();
        loadAbort?.abort();
        loadToken += 1;
        entries.value = [];
        loaded = null;
        provider = null;
        syncTotals();

        if (conversationIdRef.value) {
            loadThread(conversationIdRef.value);
        }
    });

    watch(() => conversationIdRef.value, (id) => {
        loadThread(id);
    }, { immediate: true });

    onBeforeUnmount(() => {
        abort?.abort();
        loadAbort?.abort();
    });

    return { entries, sending, loading, send, reset, clear, totals };
}
