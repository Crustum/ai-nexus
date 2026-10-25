<script setup>
import Icon from './Icon.vue';
</script>

<template>
    <div
        data-testid="chat-empty"
        class="chat-empty-state"
    >
        <Icon
            name="sparkles"
            class="chat-empty-icon"
        />
        <h2 class="chat-empty-title">
            Explore, test, and debug your AI agents
        </h2>
    </div>
</template>

<style scoped>
.chat-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.5rem;
    text-align: center;
}

.chat-empty-state .chat-empty-icon {
    width: 3rem;
    height: 3rem;
    color: var(--bs-primary);
}

.chat-empty-title {
    max-width: 28rem;
    font-size: 1.875rem;
    line-height: 1.25;
    font-weight: 600;
    margin: 0;
}
</style>
