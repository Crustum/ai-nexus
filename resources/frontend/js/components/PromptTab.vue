<script setup>
import CopyButton from './CopyButton.vue';
import Markdown from './Markdown.vue';

const props = defineProps({
    agent: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <div
        v-if="!(props.agent.instructions ?? '').trim()"
        class="card p-4 text-center small text-muted"
    >
        This agent has no instructions.
    </div>
    <div
        v-else
        data-testid="prompt"
        class="card"
    >
        <div class="card-header d-flex align-items-center justify-content-between">
            <span class="small text-uppercase text-muted">Instructions</span>
            <CopyButton :value="(props.agent.instructions ?? '').trim()" />
        </div>
        <div class="card-body small text-muted">
            <Markdown :text="(props.agent.instructions ?? '').trim()" />
        </div>
    </div>
</template>
