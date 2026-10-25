<script setup>
import Icon from './Icon.vue';
import Markdown from './Markdown.vue';
import ReasoningPane from './ReasoningPane.vue';
import StructuredOutputCard from './StructuredOutputCard.vue';

const props = defineProps({
    entry: {
        type: Object,
        required: true,
    },
    agentModel: {
        type: String,
        default: null,
    },
});

function cacheTitle() {
    const usage = props.entry.usage;

    if (!usage) {
        return null;
    }

    const parts = [];

    if ((usage.cache_read_input_tokens ?? 0) > 0) {
        parts.push(`Cache read: ${usage.cache_read_input_tokens}`);
    }

    if ((usage.cache_write_input_tokens ?? 0) > 0) {
        parts.push(`Cache write: ${usage.cache_write_input_tokens}`);
    }

    return parts.length > 0 ? parts.join(' · ') : null;
}

function usageLine() {
    const usage = props.entry.usage;

    if (!usage) {
        return null;
    }

    const prompt = usage.input_tokens ?? 0;
    const completion = usage.output_tokens ?? 0;

    return { prompt, completion, total: prompt + completion };
}

function modelLine() {
    const meta = props.entry.meta;

    if (!meta?.model || !props.agentModel || meta.model === props.agentModel) {
        return null;
    }

    return `on ${meta.model}`;
}
</script>

<template>
    <div
        v-if="props.entry.kind === 'user'"
        class="d-flex flex-column align-items-end"
    >
        <div
            v-if="props.entry.content !== ''"
            data-testid="message-user"
            class="message-user-bubble"
        >
            <p
                class="mb-0"
                style="white-space: pre-wrap; overflow-wrap: break-word;"
            >
                {{ props.entry.content }}
            </p>
        </div>
        <div
            v-if="(props.entry.attachments ?? []).length > 0"
            data-testid="message-attachments"
            class="mt-2 d-flex flex-wrap gap-2 justify-content-end"
        >
            <template
                v-for="(attachment, index) in props.entry.attachments"
                :key="`${props.entry.id}-att-${index}`"
            >
                <a
                    v-if="String(attachment.type ?? '').startsWith('image/')"
                    :href="attachment.url"
                    target="_blank"
                    rel="noopener"
                    class="message-attachment-thumb"
                ><img
                    :src="attachment.url"
                    :alt="attachment.name"
                    loading="lazy"
                ></a>
                <a
                    v-else
                    :href="attachment.url"
                    target="_blank"
                    rel="noopener"
                    data-testid="file-chip"
                    class="badge badge-secondary"
                >{{ attachment.name }}</a>
            </template>
        </div>
    </div>
    <div v-else-if="props.entry.kind === 'assistant'">
        <div
            v-if="props.entry.pending"
            data-testid="message-assistant-loading"
            class="text-muted"
            role="status"
            aria-live="polite"
        >
            Loading...
        </div>
        <div
            v-else
            data-testid="message-assistant"
        >
            <ReasoningPane
                v-if="(props.entry.reasoning ?? '') !== '' || props.entry.reasoningStreaming"
                :text="props.entry.reasoning ?? ''"
                :streaming="props.entry.reasoningStreaming"
            />
            <Markdown
                v-if="props.entry.text && !props.entry.structured"
                :text="props.entry.text"
            />
            <span
                v-if="props.entry.streaming"
                class="streaming-caret"
                aria-hidden="true"
            />
            <StructuredOutputCard
                v-if="props.entry.structured"
                :data="props.entry.structured"
            />
            <div
                v-if="!props.entry.streaming && (usageLine() || modelLine())"
                data-testid="message-meta"
                class="small text-muted mt-1"
                :title="cacheTitle()"
            >
                <span v-if="usageLine()">Prompt: {{ usageLine().prompt }} Completion: {{ usageLine().completion }}<template v-if="(props.entry.usage?.reasoning_tokens ?? 0) > 0"> Reasoning: {{ props.entry.usage.reasoning_tokens }}</template> Total: {{ usageLine().total }}</span>
                <span v-if="modelLine()"> · {{ modelLine() }}</span>
                <span
                    v-if="props.entry.durationMs !== null && props.entry.durationMs !== undefined"
                    class="d-inline-flex align-items-center gap-1"
                > · <Icon name="timer" />{{ props.entry.durationMs }}ms</span>
            </div>
        </div>
    </div>
</template>
