<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue';
import Icon from './Icon.vue';
import { MENU_OPEN_EVENT, announceMenuOpen, menuUid } from '../utils/menus';

const props = defineProps({
    ariaLabel: {
        type: String,
        default: null,
    },
    testId: {
        type: String,
        default: null,
    },
    title: {
        type: String,
        default: null,
    },
    triggerClass: {
        type: String,
        default: 'ai-nexus-menu-trigger',
    },
    align: {
        type: String,
        default: 'end',
    },
});

const open = ref(false);
const root = ref(null);
const menu = ref(null);
const uid = menuUid();

function toggle() {
    if (open.value) {
        close();
    } else {
        open.value = true;
        announceMenuOpen(uid);
        nextTick(position);
    }
}

function close() {
    open.value = false;
}

// The menu teleports to <body> and positions itself from the trigger rect, so
// it is never clipped by an overflow container (sidebar rail, responsive
// table) and never leaves the viewport.
function position() {
    const trigger = root.value?.querySelector('button');
    const panel = menu.value;

    if (!trigger || !panel) {
        return;
    }

    const rect = trigger.getBoundingClientRect();
    const width = panel.offsetWidth;
    const height = panel.offsetHeight;

    let left = props.align === 'end' ? rect.right - width : rect.left;

    left = Math.max(8, Math.min(left, window.innerWidth - width - 8));

    let top = rect.bottom + 4;

    if (top + height > window.innerHeight - 8) {
        top = Math.max(8, rect.top - height - 4);
    }

    panel.style.left = `${left}px`;
    panel.style.top = `${top}px`;
}

function onDocumentClick(event) {
    if (root.value && !root.value.contains(event.target)) {
        close();
    }
}

function onSiblingOpen(event) {
    if (event.detail !== uid) {
        close();
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        close();
    }
}

function onViewportChange() {
    if (open.value) {
        position();
    }
}

onMounted(() => {
    window.addEventListener(MENU_OPEN_EVENT, onSiblingOpen);
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
    window.addEventListener('scroll', onViewportChange, true);
    window.addEventListener('resize', onViewportChange);
});

onBeforeUnmount(() => {
    window.removeEventListener(MENU_OPEN_EVENT, onSiblingOpen);
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
    window.removeEventListener('scroll', onViewportChange, true);
    window.removeEventListener('resize', onViewportChange);
});

defineExpose({ close });
</script>

<template>
    <div
        ref="root"
        class="ai-nexus-dropdown d-inline-block"
    >
        <button
            :class="props.triggerClass"
            :aria-label="props.ariaLabel"
            :data-testid="props.testId"
            :title="props.title"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click.stop.prevent="toggle()"
        >
            <slot name="trigger">
                <Icon name="dots" />
            </slot>
        </button>
        <Teleport to="body">
            <div
                v-if="open"
                ref="menu"
                class="dropdown-menu show ai-nexus-menu"
                role="menu"
                @click="close()"
            >
                <slot />
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.ai-nexus-menu-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    padding: 0;
    border: none;
    border-radius: 0.375rem;
    background: transparent;
    color: var(--bs-secondary-color);
}

.ai-nexus-menu-trigger:hover {
    color: var(--bs-body-color);
}

.ai-nexus-menu {
    position: fixed;
    z-index: 1060;
    min-width: 12rem;
    margin: 0;
}
</style>

