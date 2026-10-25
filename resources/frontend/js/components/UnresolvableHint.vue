<script setup>
const props = defineProps({
    kind: {
        type: String,
        default: null,
    },
    dependencies: {
        type: Array,
        default: () => [],
    },
    error: {
        type: String,
        default: null,
    },
});

function shortName(type) {
    if (!type) {
        return 'unknown';
    }

    const parts = String(type).split('\\');

    return parts[parts.length - 1] ?? type;
}

function hintFor(dependency) {
    const { parameter, type, reason } = dependency;
    const name = shortName(type);

    switch (reason) {
        case 'unbound_interface':
            return {
                what: `$${parameter} needs ${name}, an interface with no binding.`,
                fix: `Bind it in a service provider: $this->app->bind(${name}::class, YourImplementation::class);`,
            };
        case 'unbound_abstract':
            return {
                what: `$${parameter} needs ${name}, an abstract class with no binding.`,
                fix: `Bind a concrete implementation: $this->app->bind(${name}::class, YourImplementation::class);`,
            };
        case 'missing_class':
            return {
                what: `$${parameter} is type-hinted ${name}, which does not exist.`,
                fix: 'Fix the import or the type-hint, then refresh.',
            };
        case 'primitive':
            return {
                what: `$${parameter} is a ${name} value, which the container cannot autowire.`,
                fix: 'Give it a default value, or resolve it inside the agent instead of the constructor.',
            };
        case 'untyped':
            return {
                what: `$${parameter} has no type-hint.`,
                fix: 'Add a type-hint the container can resolve, or give the parameter a default value.',
            };
        default:
            return {
                what: `$${parameter} uses a union or intersection type.`,
                fix: 'Type-hint a single class or interface the container can resolve.',
            };
    }
}
</script>

<template>
    <div
        data-testid="unresolvable-hint"
        class="card card-bg-secondary p-3 small"
    >
        <template v-if="props.kind !== 'binding'">
            <p class="fw-medium mb-1">
                This agent threw while starting up
            </p>
            <code class="d-block text-muted">{{ props.error ?? 'Unknown error.' }}</code>
        </template>
        <template v-else-if="props.dependencies.length === 0">
            <p class="fw-medium mb-1">
                The container could not build one of this agent's dependencies
            </p>
            <code class="d-block text-muted">{{ props.error ?? 'Unknown binding error.' }}</code>
        </template>
        <template v-else>
            <p class="fw-medium mb-2">
                AiNexus can't construct this agent
            </p>
            <div
                v-for="dependency in props.dependencies"
                :key="dependency.parameter"
                class="mb-2"
            >
                <p class="text-muted mb-1">
                    {{ hintFor(dependency).what }}
                </p>
                <code class="d-block text-muted">{{ hintFor(dependency).fix }}</code>
            </div>
        </template>
    </div>
</template>
