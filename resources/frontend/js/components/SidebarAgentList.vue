<script setup>
import { RouterLink } from 'vue-router';

const props = defineProps({
    agents: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <p
        v-if="props.loading"
        class="px-2 small text-muted"
    >
        Loading…
    </p>
    <p
        v-else-if="props.agents.length === 0"
        class="px-2 small text-muted"
    >
        No agents discovered.
    </p>
    <ul
        v-else
        data-testid="sidebar-agents"
        class="nav flex-column"
    >
        <li
            v-for="agent in props.agents"
            :key="agent.slug"
            class="nav-item min-w-0"
        >
            <RouterLink
                :to="`/playground/${agent.slug}`"
                class="nav-link d-flex align-items-center"
                active-class="active"
            >
                <span
                    class="text-truncate flex-fill small text-uppercase"
                    :title="agent.name"
                    data-testid="sidebar-agent-name"
                >{{ agent.name }}</span>
            </RouterLink>
        </li>
    </ul>
</template>

<style scoped>
.min-w-0 {
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
}
</style>
