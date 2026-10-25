<script setup>
import { RouterLink } from 'vue-router';
import Icon from './Icon.vue';
import SchemaParameterRow from './SchemaParameterRow.vue';

const props = defineProps({
    tool: {
        type: Object,
        required: true,
    },
});

const kindLabels = { provider_tool: 'Provider tool', agent: 'Agent tool', mcp: 'MCP' };
</script>

<template>
    <section
        data-testid="tool-detail"
        class="card p-3"
    >
        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
            <h3 class="small text-uppercase text-muted fw-medium mb-0">
                {{ props.tool.name }}
            </h3>
            <span
                v-if="kindLabels[props.tool.type]"
                class="badge badge-tier flex-shrink-0"
            >{{ kindLabels[props.tool.type] }}</span>
        </div>
        <p
            v-if="props.tool.description"
            class="small text-muted tool-detail-text"
        >
            {{ props.tool.description }}
        </p>
        <p
            v-if="props.tool.schema_error"
            class="small text-danger d-flex align-items-center gap-1"
        >
            <Icon name="alert" />Schema unavailable: {{ props.tool.schema_error }}
        </p>
        <div
            v-if="(props.tool.parameters ?? []).length > 0"
            class="d-flex flex-column gap-2"
        >
            <SchemaParameterRow
                v-for="parameter in props.tool.parameters"
                :key="parameter.name"
                :parameter="parameter"
            />
        </div>
        <div
            v-if="Object.keys(props.tool.provider_options ?? {}).length > 0"
            class="d-flex flex-column gap-2 mt-2"
        >
            <div
                v-for="(value, key) in props.tool.provider_options"
                :key="key"
                class="d-flex flex-column align-items-stretch gap-1 small w-100"
            >
                <span class="text-muted">{{ key }}</span>
                <span
                    class="badge badge-chip align-self-start text-break"
                    style="white-space: normal; text-align: left;"
                >{{ String(value) }}</span>
            </div>
        </div>
        <RouterLink
            v-if="props.tool.agent_slug"
            :to="`/playground/${props.tool.agent_slug}?info=1`"
            class="small d-inline-flex align-items-center gap-1 link-primary"
        >
            Inspect {{ props.tool.name }} <Icon name="arrowUpRight" />
        </RouterLink>
        <p
            v-if="(props.tool.parameters ?? []).length === 0 && Object.keys(props.tool.provider_options ?? {}).length === 0 && !props.tool.agent_slug && !props.tool.schema_error"
            class="small text-muted mb-0"
        >
            No parameters
        </p>
    </section>
</template>
