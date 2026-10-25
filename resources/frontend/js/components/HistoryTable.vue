<script setup>
import HistoryRow from './HistoryRow.vue';
import EmptyState from './EmptyState.vue';

const props = defineProps({
    conversations: {
        type: Array,
        default: () => [],
    },
    meta: {
        type: Object,
        default: () => ({ current_page: 1, last_page: 1, per_page: 25, total: 0 }),
    },
    loading: {
        type: Boolean,
        default: false,
    },
    filtered: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['page', 'open', 'rename', 'delete', 'clear-filters']);

const columns = ['Agent', 'Message', 'Status', 'Tokens', 'Date & Time', ''];

function visiblePages() {
    const current = props.meta.current_page ?? 1;
    const last = props.meta.last_page ?? 1;
    const start = Math.max(1, Math.min(current - 2, last - 4));
    const end = Math.min(last, start + 4);
    const pages = [];

    for (let page = start; page <= end; page += 1) {
        pages.push(page);
    }

    return pages;
}
</script>

<template>
    <div>
        <div v-if="props.loading && props.conversations.length === 0">
            <div
                v-for="n in 5"
                :key="n"
                class="placeholder-glow mb-2"
            >
                <span class="placeholder col-12" />
            </div>
        </div>
        <div
            v-else-if="props.conversations.length === 0 && props.filtered"
            data-testid="history-no-matches"
        >
            <EmptyState title="No conversations match these filters">
                <p>
                    Nothing here fits what you're looking for.
                    <button
                        type="button"
                        class="btn btn-link p-0 align-baseline"
                        @click="emit('clear-filters')"
                    >
                        Clear the filters
                    </button>
                    to see everything again.
                </p>
            </EmptyState>
        </div>
        <div
            v-else-if="props.conversations.length === 0"
            data-testid="history-empty"
        >
            <EmptyState title="No conversations yet">
                <p>Open an agent from Discovery and send it a message — every conversation you have shows up here.</p>
            </EmptyState>
        </div>
        <div v-else>
            <div class="table-responsive">
                <table
                    class="table"
                    data-testid="history-table"
                >
                    <thead>
                        <tr>
                            <th
                                v-for="(column, index) in columns"
                                :key="`col-${index}`"
                            >
                                {{ column }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <HistoryRow
                            v-for="conversation in props.conversations"
                            :key="conversation.id"
                            :conversation="conversation"
                            @open="emit('open', conversation)"
                            @rename="emit('rename', conversation)"
                            @delete="emit('delete', conversation)"
                        />
                    </tbody>
                </table>
            </div>
            <nav
                v-if="props.meta.last_page > 1"
                class="d-flex align-items-center gap-2 flex-wrap"
                aria-label="History pages"
            >
                <button
                    class="btn btn-sm btn-outline-secondary"
                    :disabled="props.meta.current_page <= 1"
                    @click.prevent="emit('page', props.meta.current_page - 1)"
                >
                    Previous
                </button>
                <button
                    v-for="page in visiblePages()"
                    :key="page"
                    class="btn btn-sm"
                    :class="page === props.meta.current_page ? 'btn-primary' : 'btn-outline-secondary'"
                    :aria-current="page === props.meta.current_page ? 'page' : null"
                    @click.prevent="emit('page', page)"
                >
                    {{ page }}
                </button>
                <button
                    class="btn btn-sm btn-outline-secondary"
                    :disabled="props.meta.current_page >= props.meta.last_page"
                    @click.prevent="emit('page', props.meta.current_page + 1)"
                >
                    Next
                </button>
            </nav>
        </div>
    </div>
</template>
