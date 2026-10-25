<script setup>
const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    count: {
        type: Number,
        default: undefined,
    },
});
</script>

<template>
    <section
        data-testid="info-section"
        class="card p-3"
    >
        <h3 class="small text-uppercase text-muted fw-medium mb-3">
            {{ props.title }}<template v-if="props.count !== undefined"> ({{ props.count }})</template>
        </h3>
        <div class="d-flex flex-column gap-2">
            <slot />
        </div>
    </section>
</template>
