<script setup>
import { ref } from 'vue';
import DropdownMenu from './DropdownMenu.vue';
import Icon from './Icon.vue';

const props = defineProps({
    models: {
        type: Array,
        default: () => [],
    },
    selected: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['select']);

const menu = ref(null);
const tierLabels = { agent: 'agent default', cheapest: 'cheapest', smartest: 'smartest', configured: 'configured' };

function current() {
    return props.models.find((model) => model.id === props.selected) ?? props.models[0] ?? { label: '', id: null, tier: 'agent' };
}

function choose(model) {
    // Selecting the agent's own model clears the override rather than
    // pinning it, so the request stays identical to an untouched one.
    emit('select', model.tier === 'agent' ? null : model.id);
    menu.value?.close();
}
</script>

<template>
    <div v-if="props.models.length > 0">
        <DropdownMenu
            ref="menu"
            test-id="model-selector"
            :aria-label="`Model: ${current().label}`"
            trigger-class="model-trigger"
            align="end"
        >
            <template #trigger>
                <Icon name="sparkles" />
                <span
                    class="text-truncate"
                    style="max-width: 10rem;"
                >{{ current().label }}</span>
                <Icon
                    name="chevronDown"
                    class="opacity-60"
                />
            </template>
            <button
                v-for="model in props.models"
                :key="model.id"
                class="dropdown-item"
                :class="{ active: model.id === current().id }"
                @click.prevent="choose(model)"
            >
                <span class="d-flex flex-column min-w-0 flex-fill">
                    <span class="text-truncate">{{ model.label }}</span>
                    <span
                        v-if="tierLabels[model.tier]"
                        class="small text-muted"
                    >{{ tierLabels[model.tier] }}</span>
                </span>
                <Icon
                    v-if="model.id === current().id"
                    name="check"
                />
            </button>
        </DropdownMenu>
    </div>
</template>
