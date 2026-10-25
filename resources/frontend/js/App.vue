<script setup>
import { onMounted, onBeforeUnmount, provide, reactive, ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Alert from './components/Alert.vue';
import Sidebar from './components/Sidebar.vue';
import RenameDialog from './components/RenameDialog.vue';
import DeleteDialog from './components/DeleteDialog.vue';
import { ALERT_KEY } from './composables/useAlert';
import { useConversationActions, onConversationsChanged } from './composables/useConversationActions';
import { getAgents, getConversations } from './services/api';

const COLLAPSED_KEY = 'ai-nexus-sidebar-collapsed';

const route = useRoute();
const router = useRouter();

const alert = reactive({
    type: null,
    autoClose: 0,
    message: '',
    confirmationProceed: null,
    confirmationCancel: null,
});

provide(ALERT_KEY, alert);

const collapsed = ref(localStorage.getItem(COLLAPSED_KEY) === '1');
const narrowMedia = window.matchMedia('(max-width: 860px)');
const narrow = ref(narrowMedia.matches);

// Zoomed or narrow viewports automatically ride the icon rail so the content
// never squeezes into a page-level horizontal scrollbar. The stored
// preference still applies on wide screens.
const effectiveCollapsed = computed(() => collapsed.value || narrow.value);
const isPlayground = computed(() => route.path.startsWith('/playground'));

function onNarrowChange(event) {
    narrow.value = event.matches;
}
const agents = ref([]);
const agentsLoading = ref(true);
const conversations = ref([]);
const conversationsLoading = ref(true);
const version = ref(window.AiNexus?.version ?? 'dev');

const { renaming, deleting, askRename, askDelete, rename, remove, closeDialogs } = useConversationActions(loadRecents);

function toggleSidebar() {
    collapsed.value = !collapsed.value;
    localStorage.setItem(COLLAPSED_KEY, collapsed.value ? '1' : '0');
}

function clearAlert() {
    alert.type = null;
    alert.autoClose = false;
    alert.message = '';
    alert.confirmationProceed = null;
    alert.confirmationCancel = null;
}

function openConversation(conversation) {
    if (!conversation?.id || !conversation?.agent_slug) {
        return;
    }

    router.push(`/playground/${conversation.agent_slug}?c=${conversation.id}`);
}

function loadRecents() {
    const controller = new AbortController();

    if (conversations.value.length === 0) {
        conversationsLoading.value = true;
    }

    getConversations({ page: 1 }, controller.signal)
        .then((page) => {
            conversations.value = (page.data ?? []).slice(0, 5);
        })
        .catch(() => {})
        .finally(() => {
            conversationsLoading.value = false;
        });

    return () => controller.abort();
}

onMounted(() => {
    narrowMedia.addEventListener('change', onNarrowChange);

    const controller = new AbortController();

    getAgents(controller.signal)
        .then((data) => {
            agents.value = data;
        })
        .catch(() => {})
        .finally(() => {
            agentsLoading.value = false;
        });

    const abortRecents = loadRecents();
    const unsubscribe = onConversationsChanged(loadRecents);

    return () => {
        controller.abort();
        abortRecents();
        unsubscribe();
    };
});

// A rename or delete on the History page never changes the route — refresh the
// rail on navigation too, the same as upstream.
watch(() => route.fullPath, loadRecents);

onBeforeUnmount(() => {
    narrowMedia.removeEventListener('change', onNarrowChange);
});

defineExpose({ alert, collapsed });
</script>

<template>
    <div class="ai-nexus-shell d-flex overflow-hidden w-100">
        <Alert
            v-if="alert.type"
            :message="alert.message"
            :type="alert.type"
            :auto-close="alert.autoClose"
            :confirmation-proceed="alert.confirmationProceed"
            :confirmation-cancel="alert.confirmationCancel"
            @closed="clearAlert"
        />

        <Sidebar
            :collapsed="effectiveCollapsed"
            :agents="agents"
            :agents-loading="agentsLoading"
            :conversations="conversations"
            :conversations-loading="conversationsLoading"
            :version="version"
            @toggle="toggleSidebar"
            @open-conversation="openConversation"
            @rename-conversation="askRename"
            @delete-conversation="askDelete"
        />

        <main class="ai-nexus-main flex-fill d-flex flex-column overflow-hidden">
            <div
                class="flex-fill d-flex flex-column"
                :class="isPlayground ? 'p-0 overflow-hidden' : 'p-4 overflow-y-auto'"
                style="min-height: 0;"
            >
                <RouterView />
            </div>
        </main>

        <RenameDialog
            :open="renaming !== null"
            :title="renaming?.title ?? ''"
            @close="closeDialogs"
            @save="rename"
        />
        <DeleteDialog
            :open="deleting !== null"
            :title="deleting?.title ?? ''"
            @close="closeDialogs"
            @confirm="remove"
        />
    </div>
</template>

<style scoped>
.ai-nexus-shell {
    min-height: 100vh;
}

.ai-nexus-main {
    min-width: 0;
}
</style>

