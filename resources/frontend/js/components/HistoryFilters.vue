<script setup>
import DropdownMenu from './DropdownMenu.vue';
import DateRangePicker from './DateRangePicker.vue';
import FilterMenu from './FilterMenu.vue';
import Icon from './Icon.vue';

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    options: {
        type: Object,
        default: () => ({ agents: [], tools: [] }),
    },
});

const emit = defineEmits(['change']);

function update(patch) {
    emit('change', { ...props.filters, ...patch, page: 1 });
}

function agentOptions() {
    return (props.options.agents ?? []).map((agent) => ({ value: agent.slug, label: agent.name }));
}

function toolOptions() {
    return (props.options.tools ?? []).map((tool) => ({ value: tool, label: tool }));
}

function onStatus(selected) {
    update({ status: selected[selected.length - 1] ?? null });
}
</script>

<template>
    <div class="d-flex flex-wrap align-items-center gap-3 history-filters">
        <div
            class="form-control-with-icon flex-fill"
            style="min-width: 14rem;"
        >
            <div class="icon-wrapper">
                <Icon name="search" />
            </div>
            <input
                id="historySearch"
                type="search"
                class="form-control w-100"
                placeholder="Search"
                aria-label="Search conversations"
                data-testid="history-search"
                :value="props.filters.search"
                @input="update({ search: $event.target.value })"
            >
        </div>
        <FilterMenu
            label="Agents"
            icon="bot"
            test-id="filter-agents"
            :options="agentOptions()"
            :selected="props.filters.agents ?? []"
            @change="(agents) => update({ agents })"
        />
        <FilterMenu
            label="Status"
            icon="checkCircle"
            test-id="filter-status"
            :options="[{ value: 'success', label: 'Success' }, { value: 'error', label: 'Error' }]"
            :selected="props.filters.status ? [props.filters.status] : []"
            @change="onStatus"
        />
        <FilterMenu
            label="Tools"
            icon="wrench"
            test-id="filter-tools"
            :options="toolOptions()"
            :selected="props.filters.tools ?? []"
            @change="(tools) => update({ tools })"
        />
        <DateRangePicker
            :from="props.filters.from ?? null"
            :to="props.filters.to ?? null"
            @change="({ from, to }) => update({ from, to })"
        />
        <DropdownMenu
            aria-label="Sort by"
            test-id="sort-by"
            trigger-class="btn btn-muted btn-sm d-inline-flex align-items-center gap-2"
        >
            <template #trigger>
                <Icon name="arrowUpDown" />
                Sort By: {{ props.filters.sort === 'newest' ? 'Newest First' : 'Oldest First' }}
            </template>
            <button
                class="dropdown-item"
                :class="{ active: props.filters.sort !== 'oldest' }"
                @click.prevent="update({ sort: 'newest' })"
            >
                <span class="flex-fill">Newest First</span>
                <Icon
                    v-if="props.filters.sort !== 'oldest'"
                    name="check"
                />
            </button>
            <button
                class="dropdown-item"
                :class="{ active: props.filters.sort === 'oldest' }"
                @click.prevent="update({ sort: 'oldest' })"
            >
                <span class="flex-fill">Oldest First</span>
                <Icon
                    v-if="props.filters.sort === 'oldest'"
                    name="check"
                />
            </button>
        </DropdownMenu>
    </div>
</template>
