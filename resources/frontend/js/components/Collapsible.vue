<script setup>
import { ref } from 'vue';

const props = defineProps({
    label: {
        type: String,
        default: 'Details',
    },
});

const open = ref(false);
</script>

<template>
    <div
        class="min-w-0"
        style="min-width: 0; max-width: 100%;"
    >
        <button
            type="button"
            class="collapsible-trigger"
            :aria-expanded="open"
            @click.prevent="open = !open"
        >
            <span
                class="collapsible-chevron"
                :class="{ 'collapsible-chevron-open': open }"
                aria-hidden="true"
            >▾</span>
            {{ props.label }}
        </button>
        <div
            v-if="open"
            class="mt-2 min-w-0"
            style="min-width: 0; max-width: 100%;"
        >
            <slot />
        </div>
    </div>
</template>
