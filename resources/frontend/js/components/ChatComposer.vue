<script setup>
import { nextTick, onMounted, ref } from 'vue';
import DropdownMenu from './DropdownMenu.vue';
import Icon from './Icon.vue';
import ModelSelector from './ModelSelector.vue';

const props = defineProps({
    models: {
        type: Array,
        default: () => [],
    },
    model: {
        type: String,
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    autoFocus: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['send', 'model-change']);

const message = ref('');
const files = ref([]);
const dragging = ref(false);
const dragDepth = ref(0);
const input = ref(null);
const textarea = ref(null);

const pickers = [
    { label: 'Attach File', icon: 'paperclip', accept: '' },
    { label: 'Audio', icon: 'audio', accept: 'audio/*' },
    { label: 'Image', icon: 'image', accept: 'image/*' },
];

onMounted(() => {
    autoresize();

    if (props.autoFocus && textarea.value) {
        textarea.value.focus();
    }
});

function autoresize() {
    const el = textarea.value;

    if (!el) {
        return;
    }

    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight}px`;
}

function submit() {
    if (props.disabled || (message.value.trim() === '' && files.value.length === 0)) {
        return;
    }

    emit('send', message.value, [...files.value]);
    message.value = '';
    files.value = [];
    nextTick(autoresize);
}

function pick(accept) {
    if (input.value) {
        input.value.accept = accept;
        input.value.click();
    }
}

function add(added) {
    if (added && added.length > 0) {
        files.value = [...files.value, ...Array.from(added)];
    }
}

function removeAt(index) {
    files.value = files.value.filter((_, at) => at !== index);
}
</script>

<template>
    <div
        data-testid="chat-composer"
        class="composer-card d-flex flex-column gap-3"
        @dragenter.prevent="dragDepth += 1; dragging = true"
        @dragover.prevent
        @dragleave="dragDepth -= 1; if (dragDepth <= 0) { dragging = false; }"
        @drop.prevent="dragDepth = 0; dragging = false; add($event.dataTransfer.files)"
    >
        <input
            ref="input"
            type="file"
            multiple
            hidden
            aria-hidden="true"
            @change="add($event.target.files); $event.target.value = ''"
        >
        <div
            v-if="dragging"
            data-testid="drop-zone"
            class="d-flex flex-column align-items-center gap-2 py-4 small text-muted"
        >
            Drop your file here
        </div>
        <textarea
            v-else
            ref="textarea"
            v-model="message"
            class="composer-input"
            rows="1"
            placeholder="Type your message here..."
            aria-label="Message"
            data-testid="composer-input"
            :autofocus="props.autoFocus"
            @input="autoresize"
            @keydown="(event) => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); submit(); } }"
        />
        <div class="d-flex align-items-center gap-2">
            <DropdownMenu
                aria-label="Attach a file"
                trigger-class="btn btn-sm composer-attach"
            >
                <template #trigger>
                    <Icon name="plus" />
                </template>
                <button
                    v-for="picker in pickers"
                    :key="picker.label"
                    class="dropdown-item"
                    @click.prevent="pick(picker.accept)"
                >
                    <Icon :name="picker.icon" />
                    {{ picker.label }}
                </button>
            </DropdownMenu>
            <div class="d-flex min-w-0 flex-fill flex-wrap gap-2">
                <span
                    v-for="(file, index) in files"
                    :key="`${file.name}-${index}`"
                    data-testid="file-chip"
                    class="badge badge-chip d-inline-flex align-items-center gap-2"
                >
                    {{ file.name }}
                    <button
                        class="btn btn-sm btn-link p-0 d-inline-flex align-items-center"
                        :aria-label="`Remove ${file.name}`"
                        @click.prevent="removeAt(index)"
                    >
                        <Icon name="x" />
                    </button>
                </span>
            </div>
            <ModelSelector
                :models="props.models"
                :selected="props.model"
                @select="(next) => emit('model-change', next)"
            />
            <button
                class="composer-send"
                aria-label="Send message"
                :disabled="props.disabled || (message.trim() === '' && files.length === 0)"
                @click.prevent="submit"
            >
                Send
                <Icon name="arrowRight" />
            </button>
        </div>
    </div>
</template>
