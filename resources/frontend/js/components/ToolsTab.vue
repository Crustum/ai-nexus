<script setup>
import SchemaParameterRow from './SchemaParameterRow.vue';
import ToolDetailCard from './ToolDetailCard.vue';

const props = defineProps({
    agent: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <p
        v-if="(props.agent.tools ?? []).length === 0 && !props.agent.output_schema"
        data-testid="tools-empty"
        class="card p-4 text-center small text-muted"
    >
        This agent has no tools.
    </p>
    <div
        v-else
        class="d-flex flex-column gap-3"
    >
        <ToolDetailCard
            v-for="(tool, index) in props.agent.tools"
            :key="`${tool.name}-${index}`"
            :tool="tool"
        />
        <section
            v-if="props.agent.output_schema"
            data-testid="output-schema"
            class="card p-3"
        >
            <h3 class="small text-uppercase text-muted fw-medium mb-3">
                Output schema
            </h3>
            <div class="d-flex flex-column gap-2">
                <SchemaParameterRow
                    v-for="parameter in props.agent.output_schema"
                    :key="parameter.name"
                    :parameter="parameter"
                />
            </div>
        </section>
    </div>
</template>
