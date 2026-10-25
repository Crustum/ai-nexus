<script setup>
import PageHeader from '../components/PageHeader.vue';
import AgentCard from '../components/AgentCard.vue';
import EmptyState from '../components/EmptyState.vue';
import { useAgents } from '../composables/useAgents';

const { agents, loading, error } = useAgents();
</script>

<template>
    <div class="p-2">
        <PageHeader
            title="Agents"
            :count="loading ? null : agents.length"
            subtitle="Auto-scanned from your configured agent paths on every request. Click a card to open the chat playground."
        />
        <div class="mt-4">
            <div
                v-if="loading"
                class="row g-3"
                aria-busy="true"
            >
                <div
                    v-for="n in 4"
                    :key="n"
                    class="col-12 col-md-6"
                >
                    <div class="card p-3 placeholder-glow"><span class="placeholder col-8" /><span class="placeholder col-12" /></div>
                </div>
            </div>
            <EmptyState
                v-else-if="error"
                title="Could not load agents"
            >
                <p>{{ error }}</p>
            </EmptyState>
            <EmptyState
                v-else-if="agents.length === 0"
                title="No agents found"
            >
                <p>Create a class implementing the Agent contract in <code>app/Agents/</code> to get started.</p>
            </EmptyState>
            <div
                v-else
                class="row g-3"
                data-testid="agent-grid"
            >
                <div
                    v-for="agent in agents"
                    :key="agent.slug"
                    class="col-12 col-md-6 d-flex"
                >
                    <AgentCard
                        :agent="agent"
                        class="w-100"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
