<script setup>
import { RouterLink } from 'vue-router';
import DropdownMenu from './DropdownMenu.vue';
import Icon from './Icon.vue';

const props = defineProps({
    conversations: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['open', 'rename', 'delete']);
</script>

<template>
    <p
        v-if="props.loading"
        class="px-2 small text-muted"
    >
        Loading…
    </p>
    <p
        v-else-if="props.conversations.length === 0"
        class="px-2 small text-muted"
    >
        No conversations yet.
    </p>
    <ul
        v-else
        data-testid="sidebar-conversations"
        class="sidebar-recents"
    >
        <li
            v-for="conversation in props.conversations"
            :key="conversation.id"
            class="sidebar-recent"
        >
            <RouterLink
                :to="`/playground/${conversation.agent_slug}?c=${conversation.id}`"
                class="sidebar-recent-row"
            >
                <span class="sidebar-recent-agent">{{ conversation.agent_name }}</span>
                <span class="sidebar-recent-main">
                    <span class="sidebar-recent-title">{{ conversation.title }}</span>
                    <span
                        v-if="conversation.status === 'error'"
                        class="sidebar-recent-error"
                        aria-label="This conversation contains an error"
                        title="This conversation contains an error"
                    >
                        <Icon name="alert" />
                    </span>
                    <span class="sidebar-recent-calls">{{ conversation.tool_calls }} calls</span>
                </span>
            </RouterLink>
            <DropdownMenu
                class="sidebar-recent-menu"
                :aria-label="`Recent conversation actions for ${conversation.title}`"
            >
                <button
                    class="dropdown-item"
                    @click.prevent="emit('open', conversation)"
                >
                    <Icon name="arrowUpRight" />
                    Open
                </button>
                <button
                    class="dropdown-item"
                    @click.prevent="emit('rename', conversation)"
                >
                    <Icon name="pencil" />
                    Rename
                </button>
                <button
                    class="dropdown-item text-danger"
                    @click.prevent="emit('delete', conversation)"
                >
                    <Icon name="trash" />
                    Delete
                </button>
            </DropdownMenu>
        </li>
    </ul>
</template>
