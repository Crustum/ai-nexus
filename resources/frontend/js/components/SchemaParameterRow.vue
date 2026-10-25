<script setup>
const props = defineProps({
    parameter: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="d-flex flex-column gap-1">
        <div class="d-flex align-items-start justify-content-between gap-3">
            <span class="small text-muted">
                {{ props.parameter.name }}<span
                    v-if="props.parameter.required"
                    class="ms-1 text-primary"
                    title="Required"
                >*</span>
            </span>
            <span class="badge badge-chip flex-shrink-0 text-capitalize">{{ props.parameter.type }}</span>
        </div>
        <p
            v-if="props.parameter.description"
            class="small text-muted mb-0"
        >
            {{ props.parameter.description }}
        </p>
    </div>
</template>
