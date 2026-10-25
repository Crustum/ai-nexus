<script setup>
import { ref, watch, onMounted } from 'vue';
import MessageBubble from './MessageBubble.vue';
import ToolCard from './ToolCard.vue';
import ErrorCard from './ErrorCard.vue';
import Icon from './Icon.vue';

const props = defineProps({
    entries: {
        type: Array,
        default: () => [],
    },
    agentModel: {
        type: String,
        default: null,
    },
});

const thread = ref(null);
const pinned = ref(true);

function follow() {
    const el = thread.value;

    if (el && pinned.value) {
        el.scrollTop = el.scrollHeight;
    }
}

function onScroll(event) {
    const el = event.currentTarget;

    if (!el) {
        return;
    }

    pinned.value = el.scrollHeight - el.scrollTop - el.clientHeight < 40;
}

onMounted(() => {
    follow();
});

watch(() => props.entries, follow, { deep: true });
</script>

<template>
    <div
        ref="thread"
        data-testid="chat-thread"
        class="chat-thread"
        @scroll="onScroll"
    >
        <div class="chat-thread-inner">
            <template
                v-for="entry in props.entries"
                :key="entry.id"
            >
                <MessageBubble
                    v-if="entry.kind === 'user' || entry.kind === 'assistant'"
                    :entry="entry"
                    :agent-model="props.agentModel"
                />
                <ToolCard
                    v-else-if="entry.kind === 'tool'"
                    :entry="entry"
                />
                <ErrorCard
                    v-else-if="entry.kind === 'error'"
                    :entry="entry"
                />
                <p
                    v-else-if="entry.kind === 'notice'"
                    data-testid="chat-notice"
                    class="small text-muted mb-0 d-flex align-items-center gap-2"
                >
                    <Icon name="info" />
                    {{ entry.message }}
                </p>
            </template>
        </div>
    </div>
</template>

<style scoped>
.chat-thread {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    width: 100%;
    padding: 1.5rem 2rem;
}

.chat-thread-inner {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    width: 100%;
    margin: 0 auto;
    min-width: 0;
}
</style>
