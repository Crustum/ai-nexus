<script setup>
import DropdownMenu from './DropdownMenu.vue';
import Icon from './Icon.vue';

const emit = defineEmits(['new', 'clear']);

const props = defineProps({
    canClear: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <DropdownMenu
        aria-label="Conversation actions"
        test-id="conversation-menu"
    >
        <button
            class="dropdown-item"
            @click.prevent="emit('new')"
        >
            <Icon name="plus" />
            New conversation
        </button>
        <button
            v-if="props.canClear"
            class="dropdown-item text-danger"
            @click.prevent="emit('clear')"
        >
            <Icon name="trash" />
            Clear conversation
        </button>
    </DropdownMenu>
</template>
