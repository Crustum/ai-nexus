<script setup>
import DropdownMenu from './DropdownMenu.vue';
import Icon from './Icon.vue';

const props = defineProps({
    conversation: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['open', 'rename', 'delete']);

function abbreviate(value) {
    if (value < 1000) {
        return String(value);
    }

    return `${(value / 1000).toFixed(1).replace(/\.0$/, '')}k`;
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    return `${date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })} ${date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })}`;
}
</script>

<template>
    <tr
        data-testid="history-row"
        :data-agent="props.conversation.agent_slug"
        class="history-row"
        @click="emit('open')"
    >
        <td class="text-nowrap">
            {{ props.conversation.agent_name }}
            <span
                v-if="!props.conversation.agent_available"
                class="small text-muted"
                title="This agent no longer exists. The conversation is read-only."
            >(removed)</span>
        </td>
        <td class="text-truncate history-title">
            {{ props.conversation.title }}
        </td>
        <td>
            <span
                data-testid="history-status"
                :data-status="props.conversation.status"
                :title="props.conversation.status === 'error' ? 'A message in this conversation failed' : 'Every message succeeded'"
                :class="props.conversation.status === 'error' ? 'text-danger' : 'text-success'"
                class="d-inline-flex"
            >
                <Icon :name="props.conversation.status === 'error' ? 'alert' : 'checkCircle'" />
            </span>
        </td>
        <td class="text-nowrap text-muted">
            {{ abbreviate(props.conversation.total_tokens ?? 0) }}
        </td>
        <td class="text-nowrap text-muted">
            {{ formatDate(props.conversation.updated_at) }}
        </td>
        <td>
            <DropdownMenu
                :aria-label="`Actions for ${props.conversation.title}`"
                test-id="history-row-menu"
            >
                <button
                    class="dropdown-item"
                    @click.prevent="emit('open')"
                >
                    <Icon name="arrowUpRight" />
                    Open
                </button>
                <button
                    class="dropdown-item"
                    @click.prevent="emit('rename')"
                >
                    <Icon name="pencil" />
                    Rename
                </button>
                <button
                    class="dropdown-item text-danger"
                    @click.prevent="emit('delete')"
                >
                    <Icon name="trash" />
                    Delete
                </button>
            </DropdownMenu>
        </td>
    </tr>
</template>

<style scoped>
.history-row {
    cursor: pointer;
}

.history-title {
    max-width: 24rem;
}
</style>
