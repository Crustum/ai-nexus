<script setup>
import Collapsible from './Collapsible.vue';

const props = defineProps({
    text: {
        type: String,
        default: '',
    },
    streaming: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <p
        v-if="props.streaming"
        data-testid="reasoning"
        class="d-flex align-items-center gap-2 small text-muted mb-0"
    >
        ✦ Thinking…
    </p>
    <div
        v-else-if="props.text !== ''"
        data-testid="reasoning"
        class="mb-3"
    >
        <Collapsible label="Thinking">
            <div
                class="border-start ps-3 small text-muted"
                style="white-space: pre-wrap;"
            >
                {{ props.text }}
            </div>
        </Collapsible>
    </div>
</template>
