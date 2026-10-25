<script setup>
import { computed, onMounted, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '../components/PageHeader.vue';
import HistoryFilters from '../components/HistoryFilters.vue';
import HistoryTable from '../components/HistoryTable.vue';
import RenameDialog from '../components/RenameDialog.vue';
import DeleteDialog from '../components/DeleteDialog.vue';
import { useConversations } from '../composables/useConversations';
import { useConversationActions, onConversationsChanged } from '../composables/useConversationActions';
import { activeFilterCount, emptyFilters, fromParams, toParams } from '../utils/filters';

const route = useRoute();
const router = useRouter();

const filters = computed(() => fromParams(route.query));

const { conversations, options, meta, loading, error, refresh } = useConversations(filters);
const { renaming, deleting, askRename, askDelete, rename, remove, closeDialogs } = useConversationActions(refresh);

function setFilters(next) {
    router.replace({ query: toParams(next) });
}

function open(conversation) {
    router.push(`/playground/${conversation.agent_slug}?c=${conversation.id}`);
}

let unsubscribe = null;

onMounted(() => {
    unsubscribe = onConversationsChanged(refresh);
});

onBeforeUnmount(() => {
    if (unsubscribe) {
        unsubscribe();
    }
});
</script>

<template>
    <div class="p-2">
        <PageHeader
            title="History"
            :count="meta.total"
            subtitle="View your previous conversations and continue where you left off"
        />
        <div class="mt-4">
            <HistoryFilters
                :filters="filters"
                :options="options"
                @change="setFilters"
            />
        </div>
        <div class="mt-4">
            <p
                v-if="error"
                class="text-danger"
            >
                {{ error }}
            </p>
            <HistoryTable
                v-else
                :conversations="conversations"
                :meta="meta"
                :loading="loading"
                :filtered="activeFilterCount(filters) > 0"
                @page="(page) => setFilters({ ...filters, page })"
                @open="open"
                @rename="askRename"
                @delete="askDelete"
                @clear-filters="() => setFilters(emptyFilters())"
            />
        </div>
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
