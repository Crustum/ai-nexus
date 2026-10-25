<script setup>
import { ref, watch } from 'vue';
import ConfigTab from './ConfigTab.vue';
import Icon from './Icon.vue';
import PromptTab from './PromptTab.vue';
import ToolsTab from './ToolsTab.vue';
import UnresolvableHint from './UnresolvableHint.vue';

const props = defineProps({
    agent: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: null,
    },
    tab: {
        type: String,
        default: 'config',
    },
});

const emit = defineEmits(['tab-change', 'close']);

const tabs = [
    { value: 'config', label: 'Config' },
    { value: 'prompt', label: 'Prompt' },
    { value: 'tools', label: 'Tools' },
];

const innerTab = ref(props.tab);

watch(() => props.tab, (next) => {
    innerTab.value = next;
});
</script>

<template>
    <aside
        data-testid="info-panel"
        class="d-flex flex-column border-start flex-shrink-0"
        style="width: 20rem;"
    >
        <div
            class="d-flex align-items-center justify-content-between border-bottom px-3"
            style="min-height: 3.5rem;"
        >
            <span class="fw-medium">ⓘ Info</span>
            <button
                class="ai-nexus-icon-btn"
                aria-label="Close info panel"
                @click.prevent="emit('close')"
            >
                <Icon name="x" />
            </button>
        </div>
        <div class="d-flex flex-column gap-3 flex-fill overflow-auto p-3">
            <div
                v-if="props.loading"
                class="d-flex flex-column gap-2"
                aria-busy="true"
            >
                <div class="placeholder-glow"><span class="placeholder col-8" /></div>
                <div class="placeholder-glow"><span class="placeholder col-12" /></div>
                <div class="placeholder-glow"><span class="placeholder col-6" /></div>
            </div>
            <p
                v-else-if="props.error"
                class="text-danger small"
            >
                {{ props.error }}
            </p>
            <UnresolvableHint
                v-else-if="props.agent && !props.agent.available"
                :kind="props.agent.error_kind"
                :dependencies="props.agent.unresolvable ?? []"
                :error="props.agent.error"
            />
            <template v-else-if="props.agent">
                <ul class="nav nav-pills info-tabs">
                    <li
                        v-for="option in tabs"
                        :key="option.value"
                        class="nav-item"
                    >
                        <button
                            class="nav-link"
                            :class="{ active: (innerTab ?? props.tab) === option.value }"
                            @click.prevent="innerTab = option.value; emit('tab-change', option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </li>
                </ul>
                <ConfigTab
                    v-if="(innerTab ?? props.tab) === 'config'"
                    :agent="props.agent"
                />
                <PromptTab
                    v-else-if="(innerTab ?? props.tab) === 'prompt'"
                    :agent="props.agent"
                />
                <ToolsTab
                    v-else
                    :agent="props.agent"
                />
            </template>
        </div>
    </aside>
</template>

