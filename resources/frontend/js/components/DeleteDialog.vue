<script setup>
const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <div
        v-if="props.open"
        class="modal show d-block"
        data-testid="delete-dialog"
        role="dialog"
        aria-modal="true"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-truncate">
                        Delete conversation
                    </h5>
                </div>
                <div class="modal-body">
                    <p>Delete “{{ props.title }}”?</p>
                    <p class="small text-muted mb-0">This action cannot be undone. The messages, tool calls and any attached files will be removed.</p>
                </div>
                <div class="modal-footer">
                    <button
                        class="btn btn-secondary"
                        @click.prevent="emit('close')"
                    >
                        Cancel
                    </button>
                    <button
                        class="btn btn-danger"
                        aria-label="Confirm delete"
                        @click.prevent="emit('confirm')"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
