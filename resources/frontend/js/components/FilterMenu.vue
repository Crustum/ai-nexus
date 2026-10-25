<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import Icon from './Icon.vue';
import { MENU_OPEN_EVENT, announceMenuOpen, menuUid } from '../utils/menus';

const FILTER_OPEN_EVENT = MENU_OPEN_EVENT;

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    icon: {
        type: String,
        default: null,
    },
    options: {
        type: Array,
        default: () => [],
    },
    selected: {
        type: Array,
        default: () => [],
    },
    testId: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['change']);

const open = ref(false);
const uid = menuUid();

function toggle(value, checked) {
    if (checked) {
        emit('change', [...props.selected, value]);
    } else {
        emit('change', props.selected.filter((item) => item !== value));
    }
}

function setOpen(value) {
    open.value = value;

    // Only one popup menu at a time: opening one closes the rest.
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
    if (!event.target.closest?.('[data-filter-menu]')) {
        open.value = false;
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

onMounted(() => {
    window.addEventListener(FILTER_OPEN_EVENT, onSiblingOpen);
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener(FILTER_OPEN_EVENT, onSiblingOpen);
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div
        data-filter-menu
        class="position-relative"
    >
        <button
            class="btn btn-muted btn-sm d-inline-flex align-items-center gap-2"
            :class="{ active: props.selected.length > 0 }"
            :data-testid="props.testId"
            :aria-expanded="open"
            @click.prevent="setOpen(!open)"
        >
            <Icon
                v-if="props.icon"
                :name="props.icon"
            />
            {{ props.label }}
            <span
                v-if="props.selected.length > 0"
                :aria-label="`${props.selected.length} selected`"
                class="filter-count"
            >{{ props.selected.length }}</span>
            <Icon name="chevronDown" />
        </button>
        <div
            v-if="open"
            class="dropdown-menu show p-1 overflow-auto filter-menu"
        >
            <p
                v-if="props.options.length === 0"
                class="px-2 py-1 small text-muted mb-0"
            >
                Nothing to filter by yet.
            </p>
            <label
                v-for="option in props.options"
                :key="option.value"
                class="dropdown-item filter-option mb-0"
            >
                <input
                    type="checkbox"
                    class="form-check-input"
                    :checked="props.selected.includes(option.value)"
                    @change="toggle(option.value, $event.target.checked)"
                >
                <span>{{ option.label }}</span>
            </label>
        </div>
    </div>
</template>

<style scoped>
.btn-muted.active .filter-count {
    background: #fff;
    color: var(--bs-primary);
}

.filter-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.1rem;
    height: 1.1rem;
    padding: 0 0.25rem;
    border-radius: 9999px;
    background: var(--bs-primary);
    color: #fff;
    font-size: 0.65rem;
    line-height: 1;
}

.filter-menu {
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 1050;
    max-height: 18rem;
    min-width: 14rem;
    margin-top: 0.25rem;
}

.filter-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}
</style>
