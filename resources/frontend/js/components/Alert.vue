<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { hideCssModal, showCssModal } from '../utils/cssModal';

const props = defineProps({
    type: { type: String, default: null },
    message: { type: String, default: '' },
    autoClose: { type: [Boolean, Number], default: false },
    confirmationProceed: { type: Function, default: null },
    confirmationCancel: { type: Function, default: null },
});

const emit = defineEmits(['closed']);

const timeout = ref(null);
const modalElement = ref(null);
const anotherModalOpened = document.body.classList.contains('modal-open');

function close() {
    clearTimeout(timeout.value);
    hideCssModal(modalElement.value, () => {
        emit('closed');

        if (anotherModalOpened) {
            document.body.classList.add('modal-open');
        }
    });
}

function confirm() {
    if (props.confirmationProceed) {
        props.confirmationProceed();
    }

    close();
}

function cancel() {
    if (props.confirmationCancel) {
        props.confirmationCancel();
    }

    close();
}

onMounted(() => {
    showCssModal(modalElement.value, {
        onEscape: () => {
            if (props.type === 'confirmation') {
                cancel();
                return;
            }

            close();
        },
    });

    if (props.autoClose) {
        timeout.value = setTimeout(() => {
            close();
        }, props.autoClose);
    }
});

onBeforeUnmount(() => {
    clearTimeout(timeout.value);
    hideCssModal(modalElement.value);
});
</script>

<template>
    <div
        id="alertModal"
        ref="modalElement"
        class="modal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="alertModalLabel"
        aria-hidden="true"
    >
        <div
            class="modal-dialog"
            role="document"
        >
            <div class="modal-content">
                <div class="modal-body">
                    <p class="m-0 py-4">
                        {{ message }}
                    </p>
                </div>

                <div class="modal-footer justify-content-start flex-row-reverse">
                    <button
                        v-if="type == 'error'"
                        class="btn btn-primary"
                        @click="close"
                    >
                        Close
                    </button>
                    <button
                        v-if="type == 'success'"
                        class="btn btn-primary"
                        @click="close"
                    >
                        Okay
                    </button>
                    <button
                        v-if="type == 'confirmation'"
                        class="btn btn-danger"
                        @click="confirm"
                    >
                        Yes
                    </button>
                    <button
                        v-if="type == 'confirmation'"
                        class="btn"
                        @click="cancel"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
