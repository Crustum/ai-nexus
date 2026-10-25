<script setup>
import JsonView from './JsonView.vue';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div
        data-testid="structured-card"
        class="card p-3 mt-1"
    >
        <p class="small text-muted mb-2">
            {} Structured output
        </p>
        <JsonView
            :value="props.data"
            test-id="structured-output"
        />
    </div>
</template>
