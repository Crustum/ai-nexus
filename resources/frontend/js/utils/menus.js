// Only one popup menu at a time: dropdowns and filter menus announce when
// they open, and every other instance closes itself.
export const MENU_OPEN_EVENT = 'ai-nexus-menu-open';

export function menuUid() {
    return `menu-${Math.random().toString(36).slice(2)}`;
}

export function announceMenuOpen(uid) {
    window.dispatchEvent(new CustomEvent(MENU_OPEN_EVENT, { detail: uid }));
}

