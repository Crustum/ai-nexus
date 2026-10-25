import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { getConversations } from '../services/api';

const SEARCH_DEBOUNCE_MS = 250;

export function useConversations(filtersRef) {
    const conversations = ref([]);
    const options = ref({ agents: [], tools: [] });
    const meta = ref({ current_page: 1, last_page: 1, per_page: 25, total: 0 });
    const loading = ref(true);
    const error = ref(null);
    const revision = ref(0);

    function refresh() {
        revision.value += 1;
    }

    let timer = null;
    let controller = null;
    let first = true;

    function fetch() {
        if (controller) {
            controller.abort();
        }

        controller = new AbortController();
        const current = controller;
        const filters = filtersRef.value;
        const delay = first ? 0 : filters.search ? SEARCH_DEBOUNCE_MS : 0;

        first = false;
        loading.value = true;

        clearTimeout(timer);
        timer = setTimeout(() => {
            getConversations(filters, current.signal)
                .then((page) => {
                    conversations.value = page.data ?? [];
                    meta.value = page.meta ?? meta.value;
                    options.value = page.filters ?? { agents: [], tools: [] };
                    error.value = null;
                })
                .catch((reason) => {
                    if (!current.signal.aborted) {
                        error.value = reason instanceof Error ? reason.message : 'Could not load history.';
                    }
                })
                .finally(() => {
                    if (!current.signal.aborted) {
                        loading.value = false;
                    }
                });
        }, delay);
    }

    onMounted(() => {
        fetch();
    });

    onBeforeUnmount(() => {
        clearTimeout(timer);

        if (controller) {
            controller.abort();
        }
    });

    watch(
        () => [
            filtersRef.value.search,
            filtersRef.value.status,
            filtersRef.value.from,
            filtersRef.value.to,
            filtersRef.value.sort,
            filtersRef.value.page,
            (filtersRef.value.agents ?? []).join(','),
            (filtersRef.value.tools ?? []).join(','),
            revision.value,
        ],
        fetch,
    );

    return { conversations, options, meta, loading, error, refresh };
}
