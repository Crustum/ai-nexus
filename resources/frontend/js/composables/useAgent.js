import { onMounted, ref, watch } from 'vue';
import { getAgent } from '../services/api';

export function useAgent(slugRef) {
    const agent = ref(null);
    const loading = ref(true);
    const notFound = ref(false);
    const error = ref(null);

    let pending = null;

    async function load(slug) {
        if (!slug) {
            loading.value = false;
            notFound.value = true;

            return;
        }

        pending?.abort();

        const controller = new AbortController();

        pending = controller;
        loading.value = true;
        notFound.value = false;
        error.value = null;

        try {
            const detail = await getAgent(slug, controller.signal);

            if (controller.signal.aborted) {
                return;
            }

            agent.value = detail;
        } catch (cause) {
            if (controller.signal.aborted) {
                return;
            }

            const message = cause instanceof Error ? cause.message : 'Failed to load agent.';

            if (message.includes('404')) {
                notFound.value = true;
            } else {
                error.value = message;
            }
        } finally {
            if (!controller.signal.aborted) {
                loading.value = false;
            }
        }
    }

    onMounted(() => load(slugRef.value));
    watch(() => slugRef.value, load);

    return { agent, loading, notFound, error };
}
