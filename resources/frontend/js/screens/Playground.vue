<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ChatComposer from '../components/ChatComposer.vue';
import ChatEmptyState from '../components/ChatEmptyState.vue';
import ChatThread from '../components/ChatThread.vue';
import ConversationMenu from '../components/ConversationMenu.vue';
import ConversationTokens from '../components/ConversationTokens.vue';
import EmptyState from '../components/EmptyState.vue';
import InfoPanel from '../components/InfoPanel.vue';
import Icon from '../components/Icon.vue';
import { useAgent } from '../composables/useAgent';
import { useConversation } from '../composables/useConversation';
import { forget, recall, remember } from '../utils/lastConversation';
import { ref, watch } from 'vue';

const props = defineProps({
    agent: {
        type: String,
        default: null,
    },
});

const route = useRoute();
const router = useRouter();

const slug = computed(() => props.agent ?? route.params.agent ?? null);
const conversationId = computed(() => route.query.c ?? null);
const infoParam = computed(() => route.query.info ?? null);

const slugRef = computed(() => slug.value);
const conversationIdRef = computed(() => conversationId.value);

const { agent: agentDetail, loading, notFound, error } = useAgent(slugRef);
const { entries, sending, send, reset, clear, totals } = useConversation(slugRef, conversationIdRef);

const model = ref(null);

watch(() => slug.value, (next) => {
    model.value = null;

    if (next && !route.query.c) {
        const remembered = recall(next);

        if (remembered !== null) {
            router.replace({ query: { ...route.query, c: remembered } });
        }
    }
});

if (slug.value && conversationId.value === null) {
    const remembered = recall(slug.value);

    if (remembered !== null) {
        router.replace({ query: { ...route.query, c: remembered } });
    }
}

watch([() => slug.value, () => conversationId.value], ([nextSlug, nextId]) => {
    if (nextSlug && nextId) {
        remember(nextSlug, nextId);
    }
});

function dropRemembered() {
    if (conversationId.value) {
        forget(conversationId.value);
    }
}

function setConversation(id) {
    const query = { ...route.query };

    if (id) {
        query.c = id;
    } else {
        delete query.c;
    }

    router.replace({ query });
}

function setInfo(tab) {
    if (tab === null) {
        const query = { ...route.query };

        delete query.info;
        router.replace({ query });

        return;
    }

    router.replace({ query: { ...route.query, info: tab === 'config' ? '1' : tab } });
}

function infoTab() {
    if (infoParam.value === null || infoParam.value === undefined) {
        return null;
    }

    if (infoParam.value === 'prompt' || infoParam.value === 'tools') {
        return infoParam.value;
    }

    return 'config';
}

const streaming = window.AiNexus?.streaming ?? true;
</script>

<template>
    <div
        v-if="notFound && conversationId === null"
        class="p-3"
    >
        <EmptyState title="Agent not found">
            <p>No discovered agent matches <code>{{ slug }}</code>. It may have been renamed or removed — head back to Discovery.</p>
        </EmptyState>
    </div>
    <div
        v-else
        class="playground"
    >
        <div class="playground-main">
            <header class="d-flex align-items-start justify-content-between gap-3 border-bottom px-3 py-3">
                <div class="d-flex min-w-0 flex-column gap-2">
                    <div class="d-flex min-w-0 align-items-baseline gap-2">
                        <h1 class="fs-5 fw-semibold text-truncate mb-0">
                            {{ agentDetail?.name ?? (loading ? 'Loading…' : 'Agent') }}
                        </h1>
                        <span
                            v-if="agentDetail?.provider"
                            class="small text-muted text-truncate"
                        >
                            {{ agentDetail.provider }}<template v-if="agentDetail.model"> / {{ agentDetail.model }}</template><template v-else-if="agentDetail.model_tier && agentDetail.model_tier !== 'default'"> / {{ agentDetail.model_tier }}</template>
                        </span>
                    </div>
                    <ConversationTokens
                        v-if="entries.length > 0"
                        :prompt="totals.prompt"
                        :completion="totals.completion"
                    />
                    <span
                        v-if="notFound"
                        data-testid="agent-missing-notice"
                        class="badge badge-danger"
                    >This agent no longer exists — the conversation is read-only</span>
                    <span
                        v-if="agentDetail && !agentDetail.capabilities?.conversational"
                        data-testid="stateless-notice"
                        title="This agent does not keep conversation state"
                        class="badge badge-secondary d-inline-flex align-items-center gap-1"
                    ><Icon name="info" />Stateless agent — each message is sent independently.</span>
                    <span
                        v-if="!streaming"
                        data-testid="streaming-notice"
                        title="Streaming is disabled for this runtime"
                        class="badge badge-warning d-inline-flex align-items-center gap-1"
                    ><Icon name="info" />This runtime buffers responses — replies appear all at once.</span>
                </div>
                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                    <ConversationMenu
                        :can-clear="entries.length > 0"
                        @new="dropRemembered(); reset(); setConversation(null)"
                        @clear="dropRemembered(); clear().then(() => setConversation(null))"
                    />
                    <button
                        v-if="infoTab() === null"
                        class="ai-nexus-icon-btn"
                        aria-label="Open info panel"
                        title="Agent info"
                        @click.prevent="setInfo('config')"
                    >
                        <Icon name="info" />
                    </button>
                </div>
            </header>
            <div
                class="playground-thread"
                :class="{ 'playground-thread-empty': entries.length === 0 }"
            >
                <ChatEmptyState v-if="entries.length === 0" />
                <ChatThread
                    v-else
                    :entries="entries"
                    :agent-model="agentDetail?.model ?? null"
                />
            </div>
            <div class="playground-composer">
                <div class="playground-composer-inner">
                    <ChatComposer
                        :models="agentDetail?.models ?? []"
                        :model="model"
                        :disabled="sending || notFound || agentDetail?.available === false"
                        :auto-focus="entries.length === 0"
                        @send="(message, files) => send(message, setConversation, { files, model })"
                        @model-change="(next) => model = next"
                    />
                </div>
            </div>
        </div>
        <InfoPanel
            v-if="infoTab() !== null"
            :agent="agentDetail"
            :loading="loading"
            :error="error"
            :tab="infoTab()"
            @tab-change="setInfo"
            @close="setInfo(null)"
        />
    </div>
</template>

