<script setup>
const props = defineProps({
    message: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div
        data-testid="empty-state"
        class="card"
    >
        <div class="card-body text-center py-5">
            <h5
                v-if="props.title || $slots.title"
                class="card-title"
            >
                <slot name="title">
                    {{ props.title }}
                </slot>
            </h5>
            <div class="text-muted">
                <slot>{{ props.message }}</slot>
            </div>
        </div>
    </div>
</template>
