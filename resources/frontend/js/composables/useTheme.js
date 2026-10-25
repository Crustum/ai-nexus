import { onMounted, onBeforeUnmount, ref } from 'vue';

export const THEME_STORAGE_KEY = 'ai-nexus-theme';
const QUERY = '(prefers-color-scheme: dark)';
const DISABLED_MEDIA = 'max-width: 1px';

export function getStoredTheme() {
    const stored = localStorage.getItem(THEME_STORAGE_KEY);

    return stored === 'light' || stored === 'dark' || stored === 'system' ? stored : 'system';
}

export function resolveIsDark(theme) {
    if (theme === 'dark') {
        return true;
    }

    if (theme === 'light') {
        return false;
    }

    return window.matchMedia(QUERY).matches;
}

export function applyTheme(theme) {
    const dark = resolveIsDark(theme);
    const tag = document.querySelector('style[data-scheme="dark"]');

    // House dark mode is a separate stylesheet toggled by its media attribute
    // (same as Telescope/Monitor), not a class on the root element.
    if (tag) {
        tag.media = dark ? '' : DISABLED_MEDIA;

        return;
    }

    document.documentElement.classList.toggle('dark', dark);
}

export function setTheme(theme) {
    localStorage.setItem(THEME_STORAGE_KEY, theme);
    applyTheme(theme);
}

export function initTheme() {
    applyTheme(getStoredTheme());
}

export function useTheme() {
    const theme = ref(getStoredTheme());

    function select(value) {
        theme.value = value;
        setTheme(value);
    }

    function onSystemChange() {
        if (getStoredTheme() === 'system') {
            applyTheme('system');
        }
    }

    onMounted(() => {
        theme.value = getStoredTheme();
        applyTheme(theme.value);
        window.matchMedia(QUERY).addEventListener('change', onSystemChange);
    });

    onBeforeUnmount(() => {
        window.matchMedia(QUERY).removeEventListener('change', onSystemChange);
    });

    return { theme, select, applyTheme };
}

