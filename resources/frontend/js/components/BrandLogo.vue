<script setup>
const props = defineProps({
    size: {
        type: Number,
        default: 40,
    },
});
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        :width="props.size"
        :height="props.size"
        aria-hidden="true"
    >
        <circle
            cx="13"
            cy="13"
            r="9"
            fill="none"
            class="stroke-primary"
            stroke-width="3"
        />
        <line
            x1="19.5"
            y1="19.5"
            x2="25.5"
            y2="25.5"
            class="stroke-primary"
            stroke-width="3.2"
            stroke-linecap="round"
        />
        <g
            class="stroke-primary"
            stroke-width="1.8"
        >
            <line
                x1="9.5"
                y1="11"
                x2="16"
                y2="10"
            />
            <line
                x1="9.5"
                y1="11"
                x2="12.5"
                y2="16"
            />
            <line
                x1="16"
                y1="10"
                x2="12.5"
                y2="16"
            />
        </g>
        <g class="fill-primary">
            <circle
                cx="9.5"
                cy="11"
                r="2.4"
            />
            <circle
                cx="16"
                cy="10"
                r="2.4"
            />
            <circle
                cx="12.5"
                cy="16"
                r="2.4"
            />
        </g>
    </svg>
</template>
