<script setup>
const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    count: {
        type: Number,
        default: null,
    },
    subtitle: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div>
        <h1 class="fs-4">
            {{ props.title }}
            <span
                v-if="props.count !== null"
                class="text-muted"
            >({{ props.count }})</span>
        </h1>
        <p
            v-if="props.subtitle"
            class="text-muted"
        >
            {{ props.subtitle }}
        </p>
    </div>
</template>
