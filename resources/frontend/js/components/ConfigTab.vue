<script setup>
import InfoRow from './InfoRow.vue';
import InfoSection from './InfoSection.vue';
import ToolTagList from './ToolTagList.vue';

const props = defineProps({
    agent: {
        type: Object,
        default: null,
    },
});

function toolChoiceLabel(toolChoice) {
    if (!toolChoice) {
        return null;
    }

    return toolChoice.tool ? `${toolChoice.mode}: ${toolChoice.tool}` : toolChoice.mode;
}
</script>

<template>
    <div class="d-flex flex-column gap-3">
        <InfoSection title="Provider">
            <InfoRow
                label="Provider"
                :value="props.agent.provider"
            />
            <InfoRow
                v-if="props.agent.model"
                label="Model"
                :value="props.agent.model"
                accent
            />
            <InfoRow
                v-else
                label="Model"
                :value="props.agent.model_tier"
            >
                <span class="badge badge-tier">{{ props.agent.model_tier }}</span>
            </InfoRow>
            <InfoRow
                label="Class"
                :value="props.agent.class"
                mono
            />
        </InfoSection>

        <InfoSection
            v-if="props.agent.generation"
            title="Generation"
        >
            <InfoRow
                label="Temperature"
                :value="props.agent.generation.temperature"
            />
            <InfoRow
                label="Max_Tokens"
                :value="props.agent.generation.max_tokens"
            />
            <InfoRow
                label="Max_Steps"
                :value="props.agent.generation.max_steps"
            />
            <InfoRow
                label="Top_P"
                :value="props.agent.generation.top_p"
            />
            <InfoRow
                label="Tool_Choice"
                :value="toolChoiceLabel(props.agent.generation.tool_choice)"
            />
            <InfoRow
                label="Timeout"
                :value="`${props.agent.generation.timeout}s`"
            />
            <InfoRow
                label="Strict"
                :value="props.agent.generation.strict ? 'Enabled' : null"
            />
        </InfoSection>

        <InfoSection
            v-if="Object.keys(props.agent.provider_options ?? {}).length > 0"
            title="Provider options"
        >
            <InfoRow
                v-for="(value, key) in props.agent.provider_options"
                :key="key"
                :label="key"
                :value="String(value)"
            />
        </InfoSection>

        <InfoSection
            v-if="(props.agent.tools ?? []).length > 0"
            title="Capabilities"
        >
            <ToolTagList
                :tools="props.agent.tools"
                :limit="props.agent.tools.length"
            />
        </InfoSection>

        <InfoSection
            v-if="(props.agent.middleware ?? []).length > 0"
            title="Middleware"
            :count="props.agent.middleware.length"
        >
            <span
                v-for="name in props.agent.middleware"
                :key="name"
                class="d-block w-100 font-monospace small text-break px-2 py-1 rounded info-middleware"
            >{{ name }}</span>
        </InfoSection>
    </div>
</template>
