<script setup>
import { computed, ref } from 'vue';
import DropdownMenu from './DropdownMenu.vue';
import Icon from './Icon.vue';
import { getStoredTheme, setTheme } from '../composables/useTheme';

const options = [
    { value: 'light', label: 'Light', icon: 'sun' },
    { value: 'dark', label: 'Dark', icon: 'moon' },
    { value: 'system', label: 'System', icon: 'monitor' },
];

const props = defineProps({
    collapsed: {
        type: Boolean,
        default: false,
    },
});

const theme = ref(getStoredTheme());
const menu = ref(null);

const current = computed(() => options.find((option) => option.value === theme.value) ?? options[2]);

function select(value) {
    theme.value = value;
    setTheme(value);
    menu.value?.close();
}
</script>

<template>
    <div data-testid="theme-switcher">
        <DropdownMenu
            ref="menu"
            :aria-label="`Theme: ${current.label}`"
            :title="`Theme: ${current.label}`"
            trigger-class="nav-link d-flex align-items-center w-100 theme-switcher-btn"
        >
            <template #trigger>
                <Icon :name="current.icon" />
                <span v-if="!props.collapsed">{{ current.label }}</span>
                <Icon
                    v-if="!props.collapsed"
                    name="chevronDown"
                    class="ms-auto opacity-60"
                />
            </template>
            <button
                v-for="option in options"
                :key="option.value"
                class="dropdown-item"
                :class="{ active: option.value === theme }"
                @click.prevent="select(option.value)"
            >
                <Icon :name="option.icon" />
                <span class="flex-fill">{{ option.label }}</span>
                <Icon
                    v-if="option.value === theme"
                    name="check"
                />
            </button>
        </DropdownMenu>
    </div>
</template>
