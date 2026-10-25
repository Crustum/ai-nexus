import { ref } from 'vue';
import { deleteConversation, renameConversation } from '../services/api';
import { forget } from '../utils/lastConversation';

const TARGET = 'ai-nexus-conversations-changed';

export function conversationsChanged() {
    window.dispatchEvent(new CustomEvent(TARGET));
}

export function onConversationsChanged(callback) {
    window.addEventListener(TARGET, callback);

    return () => window.removeEventListener(TARGET, callback);
}

export function useConversationActions(onChanged) {
    const renaming = ref(null);
    const deleting = ref(null);

    async function rename(title) {
        if (!renaming.value) {
            return;
        }

        await renameConversation(renaming.value.id, title).catch(() => null);
        renaming.value = null;

        if (onChanged) {
            onChanged();
        }

        conversationsChanged();
    }

    async function remove() {
        if (!deleting.value) {
            return;
        }

        await deleteConversation(deleting.value.id).catch(() => null);

        // A remembered pointer to a deleted conversation would strand you on an
        // empty playground the next time you opened that agent.
        forget(deleting.value.id);

        deleting.value = null;

        if (onChanged) {
            onChanged();
        }

        conversationsChanged();
    }

    return {
        renaming,
        deleting,
        askRename: (conversation) => {
            renaming.value = conversation;
        },
        askDelete: (conversation) => {
            deleting.value = conversation;
        },
        rename,
        remove,
        closeDialogs: () => {
            renaming.value = null;
            deleting.value = null;
        },
    };
}

