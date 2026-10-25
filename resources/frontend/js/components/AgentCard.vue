<script setup>
import { useRouter } from 'vue-router';
import Icon from './Icon.vue';
import ToolTagList from './ToolTagList.vue';
import UnresolvableHint from './UnresolvableHint.vue';

const props = defineProps({
    agent: {
        type: Object,
        required: true,
    },
});

const router = useRouter();

function open() {
    router.push(`/playground/${props.agent.slug}`);
}

function openInfo(event) {
    event.stopPropagation();
    router.push(`/playground/${props.agent.slug}?info=1`);
}
</script>

<template>
    <div
        v-if="!props.agent.available"
        class="card min-w-0 agent-card"
        data-testid="agent-card-unavailable"
    >
        <div class="card-body">
            <div class="d-flex align-items-start justify-content-between gap-2">
                <h3
                    class="text-truncate fs-5 fw-semibold text-muted flex-fill"
                    :title="props.agent.name"
                    data-testid="agent-card-unavailable-name"
                >
                    {{ props.agent.name }}
                </h3>
                <span
                    class="text-danger d-inline-flex"
                    :title="props.agent.error ?? 'This agent could not be instantiated.'"
                >
                    <Icon name="alert" />
                </span>
            </div>
            <div class="mt-2">
                <UnresolvableHint
                    :kind="props.agent.error_kind"
                    :dependencies="props.agent.unresolvable ?? []"
                    :error="props.agent.error"
                />
            </div>
        </div>
        <div class="card-footer">
            <span class="small text-muted">Fix the error above, then refresh</span>
        </div>
    </div>
    <div
        v-else
        class="card min-w-0 agent-card agent-card-interactive"
        data-testid="agent-card"
        role="button"
        tabindex="0"
        @click="open"
        @keydown.enter="open"
    >
        <div class="card-body">
            <h3
                class="text-truncate fs-5 fw-semibold"
                :title="props.agent.name"
                data-testid="agent-card-name"
            >
                {{ props.agent.name }}
            </h3>
            <p class="d-flex flex-wrap align-items-center gap-1 small">
                <span class="text-muted">{{ props.agent.provider ?? 'default' }}</span>
                <span class="text-muted">/</span>
                <span
                    v-if="props.agent.model"
                    class="text-primary"
                >{{ props.agent.model }}</span>
                <span
                    v-else
                    class="badge badge-tier"
                >{{ props.agent.model_tier }}</span>
            </p>
            <ToolTagList :tools="props.agent.tools ?? []" />
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <button
                class="agent-card-link agent-card-link-muted btn btn-sm d-inline-flex align-items-center gap-1 px-0"
                @click="openInfo"
            >
                <Icon name="info" />
                Info
            </button>
            <button class="agent-card-link btn btn-sm d-inline-flex align-items-center gap-1 px-0">
                Open Playground
                <Icon name="arrowRight" />
            </button>
        </div>
    </div>
</template>

<style scoped>
.min-w-0 {
    min-width: 0;
}

.agent-card-link {
    border: 0;
    background: transparent;
    appearance: none;
    color: var(--bs-body-color);
    text-decoration: none;
    cursor: pointer;
}

.agent-card-link:hover {
    color: var(--bs-body-color);
    text-decoration: none;
}

.agent-card-link-muted {
    color: var(--bs-secondary-color);
}

.agent-card-link-muted:hover {
    color: var(--bs-body-color);
}
</style>
