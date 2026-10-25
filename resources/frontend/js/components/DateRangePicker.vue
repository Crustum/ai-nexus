<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import Icon from './Icon.vue';
import { MENU_OPEN_EVENT, announceMenuOpen, menuUid } from '../utils/menus';

const props = defineProps({
    from: {
        type: String,
        default: null,
    },
    to: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['change']);

const open = ref(false);
const uid = menuUid();

function isActive() {
    return props.from !== null || props.to !== null;
}

function label() {
    return isActive() ? `${props.from ?? '…'} – ${props.to ?? '…'}` : 'Date range';
}

function setOpen(value) {
    open.value = value;

    if (value) {
        announceMenuOpen(uid);
    }
}

function onSiblingOpen(event) {
    if (event.detail !== uid) {
        open.value = false;
    }
}

function onDocumentClick(event) {
    if (!event.target.closest?.('[data-date-range]')) {
        open.value = false;
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

onMounted(() => {
    window.addEventListener(MENU_OPEN_EVENT, onSiblingOpen);
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener(MENU_OPEN_EVENT, onSiblingOpen);
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div data-date-range class="position-relative">
        <button
            class="btn btn-muted btn-sm d-inline-flex align-items-center gap-2"
            :class="{ active: isActive() }"
            data-testid="filter-dates"
            :aria-expanded="open"
            @click.prevent="setOpen(!open)"
        >
            <Icon name="calendar" />
            {{ label() }}
        </button>
        <div
            v-if="open"
            class="dropdown-menu show p-3 d-flex flex-column gap-3 date-range-menu"
        >
            <label class="d-flex flex-column gap-1 small text-muted mb-0">
                From
                <input
                    type="date"
                    class="form-control form-control-sm"
                    :value="props.from ?? ''"
                    :max="props.to ?? undefined"
                    @change="emit('change', { from: $event.target.value || null, to: props.to })"
                >
            </label>
            <label class="d-flex flex-column gap-1 small text-muted mb-0">
                To
                <input
                    type="date"
                    class="form-control form-control-sm"
                    :value="props.to ?? ''"
                    :min="props.from ?? undefined"
                    @change="emit('change', { from: props.from, to: $event.target.value || null })"
                >
            </label>
            <button
                v-if="isActive()"
                type="button"
                class="daterange-clear align-self-start"
                @click.prevent="emit('change', { from: null, to: null })"
            >
                Clear dates
            </button>
        </div>
    </div>
</template>

<style scoped>
.date-range-menu {
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 1050;
    width: 16rem;
    margin-top: 0.25rem;
}

.daterange-clear {
    padding: 0;
    border: 0;
    background: transparent;
    appearance: none;
    font-size: 0.75rem;
    color: var(--bs-secondary-color);
    cursor: pointer;
}

.daterange-clear:hover {
    color: var(--bs-body-color);
}
</style>
