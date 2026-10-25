<script setup>
import Icon from './Icon.vue';

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
    providerStatus: {
        type: String,
        default: null,
    },
});

const icons = {
    pending: 'clock',
    success: 'checkCircle',
    error: 'alert',
};

const badgeClass = {
    pending: 'badge-warning tool-status-pending',
    success: 'badge-success tool-status-success',
    error: 'badge-danger tool-status-error',
};

function providerTitle() {
    if (!props.providerStatus) {
        return props.status;
    }

    return `Provider reported "${props.providerStatus}"`;
}
</script>

<template>
    <span
        data-testid="tool-status"
        class="badge tool-status-badge"
        :class="badgeClass[props.status] ?? 'badge-secondary'"
        :title="providerTitle()"
    >
        <Icon :name="icons[props.status] ?? 'clock'" />
        {{ props.status }}
    </span>
</template>

<style scoped>
.tool-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    font-weight: 500;
    border: 1px solid transparent;
}

.tool-status-pending {
    border-color: currentColor;
    background: transparent;
}

.tool-status-success {
    border-color: currentColor;
    background: transparent;
}

.tool-status-error {
    border-color: currentColor;
    background: transparent;
}
</style>
