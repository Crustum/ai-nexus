<script setup>
import { ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    value: {
        type: String,
        required: true,
    },
});

const copied = ref(false);
let timer = null;

async function copy() {
    try {
        await navigator.clipboard.writeText(props.value);
        copied.value = true;
        clearTimeout(timer);
        timer = setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // Clipboard access can be denied; failing silently is fine here.
    }
}
</script>

<template>
    <button
        type="button"
        class="copy-button"
        :class="{ 'copy-button-copied': copied }"
        :title="copied ? 'Copied' : 'Copy'"
        :aria-label="copied ? 'Copied' : 'Copy'"
        @click.prevent="copy()"
    >
        <Icon :name="copied ? 'check' : 'copy'" />
    </button>
</template>

<style scoped>
.copy-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border: none;
    border-radius: 0.375rem;
    background: transparent;
    color: var(--bs-secondary-color);
}

.copy-button svg.icon {
    width: 0.875rem;
    height: 0.875rem;
}

.copy-button:hover {
    background: transparent;
    color: var(--bs-body-color);
}

.copy-button:focus {
    outline: none;
}

.copy-button:focus-visible {
    outline: none;
    color: var(--bs-body-color);
}

.copy-button-copied {
    color: var(--bs-success);
}
</style>
