import { onMounted, ref } from 'vue';
import { getAgents } from '../services/api';

export function useAgents() {
    const agents = ref([]);
    const loading = ref(true);
    const error = ref(null);

    onMounted(() => {
        const controller = new AbortController();

        getAgents(controller.signal)
            .then((data) => {
                agents.value = data;
                error.value = null;
            })
            .catch((cause) => {
                if (!controller.signal.aborted) {
                    error.value = cause instanceof Error ? cause.message : 'Failed to load agents.';
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    loading.value = false;
                }
            });

        return () => controller.abort();
    });

    return { agents, loading, error };
}
