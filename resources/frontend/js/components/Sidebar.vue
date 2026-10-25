<script setup>
import { RouterLink } from 'vue-router';
import BrandLogo from './BrandLogo.vue';
import Icon from './Icon.vue';
import SchemeToggler from './SchemeToggler.vue';
import SidebarAgentList from './SidebarAgentList.vue';
import SidebarConversationList from './SidebarConversationList.vue';

const props = defineProps({
    collapsed: {
        type: Boolean,
        default: false,
    },
    agents: {
        type: Array,
        default: () => [],
    },
    agentsLoading: {
        type: Boolean,
        default: false,
    },
    conversations: {
        type: Array,
        default: () => [],
    },
    conversationsLoading: {
        type: Boolean,
        default: false,
    },
    version: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['toggle', 'open-conversation', 'rename-conversation', 'delete-conversation']);

function agentCount() {
    return props.agents.length;
}
</script>

<template>
    <aside
        class="sidebar ai-nexus-sidebar d-flex flex-column flex-shrink-0 border-end"
        :class="props.collapsed ? 'is-collapsed' : ''"
        data-testid="sidebar"
    >
        <div class="d-flex align-items-center justify-content-between px-3 sidebar-top">
            <span
                v-if="!props.collapsed"
                class="d-flex align-items-center gap-2 fs-5 fw-semibold"
            ><BrandLogo :size="22" />AiNexus</span>
            <button
                class="ai-nexus-icon-btn flex-shrink-0"
                :title="props.collapsed ? 'Expand' : 'Collapse'"
                :aria-label="props.collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                @click.prevent="emit('toggle')"
            >
                <Icon name="panel" />
            </button>
        </div>

        <div
            v-if="!props.collapsed"
            class="ai-nexus-sidebar-body px-3 py-2"
        >
            <div class="ai-nexus-sidebar-scroll">
                <section class="mb-4">
                    <p class="px-2 pb-2 text-uppercase small fw-medium text-muted mb-1">
                        Recent Conversations
                    </p>
                    <SidebarConversationList
                        :conversations="props.conversations"
                        :loading="props.conversationsLoading"
                        @open="(conversation) => emit('open-conversation', conversation)"
                        @rename="(conversation) => emit('rename-conversation', conversation)"
                        @delete="(conversation) => emit('delete-conversation', conversation)"
                    />
                </section>
                <section>
                    <p class="px-2 pb-2 text-uppercase small fw-medium text-muted mb-1">
                        Agents
                    </p>
                    <SidebarAgentList
                        :agents="props.agents"
                        :loading="props.agentsLoading"
                    />
                </section>
            </div>
        </div>
        <div
            v-else
            class="flex-fill"
        />

        <nav class="workspace-nav border-top px-3 py-2 flex-shrink-0">
            <p
                v-if="!props.collapsed"
                class="workspace-title px-2 small text-uppercase fw-medium text-muted"
            >
                Workspace
            </p>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <RouterLink
                        to="/"
                        class="nav-link d-flex align-items-center"
                        active-class="active"
                    >
                        <Icon name="search" />
                        <span v-if="!props.collapsed">Discovery</span>
                    </RouterLink>
                </li>
                <li class="nav-item">
                    <RouterLink
                        to="/history"
                        class="nav-link d-flex align-items-center"
                        active-class="active"
                    >
                        <Icon name="clock" />
                        <span v-if="!props.collapsed">History</span>
                    </RouterLink>
                </li>
                <li class="nav-item">
                    <SchemeToggler :collapsed="props.collapsed" />
                </li>
            </ul>
        </nav>

        <div class="d-flex align-items-center justify-content-between border-top px-3 py-3 flex-shrink-0">
            <span
                v-if="!props.collapsed"
                class="small text-muted"
                data-testid="sidebar-footer"
            >v{{ props.version }} · {{ agentCount() }} agent{{ agentCount() === 1 ? '' : 's' }}</span>
        </div>
    </aside>
</template>

<style scoped>
.sidebar-top {
    min-height: 3.5rem;
}

.ai-nexus-sidebar.is-collapsed .sidebar-top {
    justify-content: center;
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}

.ai-nexus-sidebar.is-collapsed .nav-link {
    justify-content: center;
}
</style>

