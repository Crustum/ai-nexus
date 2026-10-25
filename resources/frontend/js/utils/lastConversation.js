const KEY = 'ai-nexus-last-conversation';

export function remember(slug, conversationId) {
    const all = read();

    all[slug] = conversationId;

    try {
        localStorage.setItem(KEY, JSON.stringify(all));
    } catch {
    }
}

export function recall(slug) {
    return read()[slug] ?? null;
}

export function forget(conversationId) {
    const all = read();
    let changed = false;

    for (const [slug, id] of Object.entries(all)) {
        if (id === conversationId) {
            all[slug] = null;
            changed = true;
        }
    }

    if (changed) {
        try {
            localStorage.setItem(KEY, JSON.stringify(all));
        } catch {
        }
    }
}

function read() {
    try {
        const raw = localStorage.getItem(KEY);
        const parsed = raw ? JSON.parse(raw) : {};

        return typeof parsed === 'object' && parsed !== null ? parsed : {};
    } catch {
        return {};
    }
}

