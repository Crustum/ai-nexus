<script setup>
import { nextTick, ref, watch } from 'vue';

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

const emit = defineEmits(['close', 'save']);

const name = ref(props.title);
const field = ref(null);

function save() {
    const value = name.value.trim();

    if (value === '') {
        return;
    }

    emit('save', value);
}

watch(() => props.title, (value) => {
    name.value = value;
});

watch(() => props.open, (value) => {
    if (value) {
        name.value = props.title;
        nextTick(() => field.value?.focus());
    }
});
</script>

<template>
    <div
        v-if="props.open"
        class="modal show d-block"
        data-testid="rename-dialog"
        role="dialog"
        aria-modal="true"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Rename conversation
                    </h5>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Give this conversation a clear name.</p>
                    <input
                        ref="field"
                        v-model="name"
                        class="form-control"
                        aria-label="Conversation name"
                        placeholder="Conversation Name..."
                        autofocus
                        @keydown.enter.prevent="save()"
                    >
                </div>
                <div class="modal-footer">
                    <button
                        class="btn btn-secondary"
                        @click.prevent="emit('close')"
                    >
                        Cancel
                    </button>
                    <button
                        class="btn btn-primary"
                        :disabled="name.trim() === ''"
                        @click.prevent="save()"
                    >
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
