<script setup>
import { ref } from 'vue';
import Icon from './Icon.vue';
import JsonView from './JsonView.vue';
import ToolStatusBadge from './ToolStatusBadge.vue';

const props = defineProps({
    entry: {
        type: Object,
        required: true,
    },
});

const open = ref(false);
const isProviderTool = props.entry.type === 'provider_tool';
const finished = props.entry.status !== 'pending';

function typeIcon() {
    return isProviderTool ? 'zap' : 'wrench';
}
</script>

<template>
    <div
        data-testid="tool-card"
        :data-tool-status="props.entry.status"
        class="card"
    >
        <button
            type="button"
            class="tool-card-toggle"
            :aria-expanded="open"
            :aria-label="`${props.entry.name} tool call`"
            @click="open = !open"
        >
            <Icon
                :name="typeIcon()"
                class="text-muted"
            />
            <span class="d-flex min-w-0 align-items-center gap-1 small">
                <template v-if="isProviderTool && props.entry.provider">
                    <span class="text-muted">{{ props.entry.provider }}</span>
                    <span class="text-muted">/</span>
                </template>
                <span class="text-truncate">{{ props.entry.name }}</span>
            </span>
            <span class="ms-auto d-flex align-items-center gap-3 flex-shrink-0">
                <span
                    v-if="finished && props.entry.status === 'success' && props.entry.durationMs !== null"
                    class="small text-muted d-inline-flex align-items-center gap-1"
                ><Icon name="timer" />{{ props.entry.durationMs }}ms</span>
                <ToolStatusBadge
                    v-if="!(finished && props.entry.status === 'success' && props.entry.durationMs !== null)"
                    :status="props.entry.status"
                    :provider-status="props.entry.providerStatus"
                />
                <Icon
                    name="chevronDown"
                    class="text-muted tool-card-chevron"
                    :class="{ 'tool-card-chevron-open': open }"
                />
            </span>
        </button>
        <div
            v-if="open"
            class="card-body d-flex flex-column gap-3 pt-0"
        >
            <JsonView
                label="Arguments"
                :value="props.entry.arguments"
                test-id="tool-arguments"
            />
            <JsonView
                v-if="props.entry.status === 'error'"
                label="Result"
                :value="props.entry.error"
                test-id="tool-result"
                tone="error"
            />
            <p
                v-else-if="props.entry.status === 'pending'"
                class="small text-muted mb-0"
            >
                Still running<span v-if="props.entry.providerStatus"> — provider reports “{{ props.entry.providerStatus }}”</span>.
            </p>
            <JsonView
                v-else
                label="Result"
                :value="props.entry.result"
                test-id="tool-result"
            />
        </div>
    </div>
</template>

<style scoped>
.tool-card-chevron {
    transition: transform 150ms;
}

.tool-card-chevron-open {
    transform: rotate(180deg);
}
</style>
