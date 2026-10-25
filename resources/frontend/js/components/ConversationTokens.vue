<script setup>
import Icon from './Icon.vue';

const props = defineProps({
    prompt: {
        type: Number,
        default: 0,
    },
    completion: {
        type: Number,
        default: 0,
    },
});

function abbreviate(value) {
    if (value < 1000) {
        return String(value);
    }

    return `${(value / 1000).toFixed(1).replace(/\.0$/, '')}k`;
}
</script>

<template>
    <div
        data-testid="conversation-tokens"
        class="d-flex flex-wrap gap-4 small text-muted align-items-center"
    >
        <span
            class="d-inline-flex align-items-center gap-1"
            :title="`Total ${props.prompt + props.completion} tokens`"
        ><Icon name="database" />Total {{ abbreviate(props.prompt + props.completion) }}</span>
        <span
            class="d-inline-flex align-items-center gap-1"
            :title="`Prompt ${props.prompt} tokens`"
        ><Icon name="chart" />Prompt {{ abbreviate(props.prompt) }}</span>
        <span
            class="d-inline-flex align-items-center gap-1"
            :title="`Completion ${props.completion} tokens`"
        ><Icon name="copyCheck" />Completion {{ abbreviate(props.completion) }}</span>
    </div>
</template>
