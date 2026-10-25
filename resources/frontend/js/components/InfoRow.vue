<script setup>
const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: null,
        default: null,
    },
    mono: {
        type: Boolean,
        default: false,
    },
    accent: {
        type: Boolean,
        default: false,
    },
});

function empty() {
    return props.value === null || props.value === undefined || props.value === '';
}
</script>

<template>
    <div class="d-flex align-items-start justify-content-between gap-3 small w-100">
        <span class="flex-shrink-0 text-muted">{{ props.label }}</span>
        <span
            v-if="empty()"
            class="text-muted"
        >—</span>
        <span
            v-else
            class="text-break text-end min-w-0 px-2 py-1 rounded info-row-value"
            :class="[
                props.mono ? 'font-monospace info-row-mono' : '',
                props.accent ? 'text-primary' : '',
            ]"
        >
            <slot>{{ props.value }}</slot>
        </span>
    </div>
</template>

<style scoped>
.info-row-mono {
    font-size: 0.75rem;
}
</style>
