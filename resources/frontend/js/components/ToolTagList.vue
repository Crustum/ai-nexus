<script setup>
import Icon from './Icon.vue';

const props = defineProps({
    tools: {
        type: Array,
        default: () => [],
    },
    limit: {
        type: Number,
        default: 3,
    },
});

const typeIcons = {
    tool: 'wrench',
    provider_tool: 'zap',
    agent: 'bot',
    mcp: 'plug',
    unknown: 'wrench',
};

function iconFor(type) {
    return typeIcons[type] ?? 'wrench';
}

function visible() {
    return props.tools.slice(0, props.limit);
}

function hidden() {
    return props.tools.slice(props.limit);
}
</script>

<template>
    <div>
        <p
            v-if="props.tools.length === 0"
            class="small text-muted mb-0"
        >
            No tools
        </p>
        <div
            v-else
            class="tool-tags"
        >
            <span
                v-for="(tool, index) in visible()"
                :key="`${tool.name}-${index}`"
                class="badge badge-chip"
                :title="tool.name"
            >
                <Icon :name="iconFor(tool.type)" />
                <span class="text-truncate">{{ tool.name }}</span>
            </span>
            <span
                v-if="hidden().length > 0"
                class="badge badge-chip tool-tip"
                data-testid="tool-overflow"
                :aria-label="`${hidden().length} more tools`"
                tabindex="0"
            >+ {{ hidden().length }}
                <span
                    class="tool-tip-card"
                    aria-hidden="true"
                >
                    <span class="tool-tip-title">Tools ({{ props.tools.length }})</span>
                    <span
                        v-for="(tool, index) in props.tools"
                        :key="`${tool.name}-all-${index}`"
                    >{{ tool.name }}</span>
                </span>
            </span>
        </div>
    </div>
</template>

<style scoped>
.tool-tags {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}
</style>
