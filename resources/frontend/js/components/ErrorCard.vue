<script setup>
import Collapsible from './Collapsible.vue';
import CopyButton from './CopyButton.vue';
import Icon from './Icon.vue';
import JsonView from './JsonView.vue';

const props = defineProps({
    entry: {
        type: Object,
        required: true,
    },
});

function copyText() {
    return [props.entry.exceptionClass, props.entry.message, props.entry.stackTrace]
        .filter(Boolean)
        .join('\n\n');
}
</script>

<template>
    <div
        data-testid="error-card"
        class="error-card p-3"
        :class="props.entry.recoverable ? 'error-card-muted' : 'error-card-fatal'"
    >
        <div class="d-flex align-items-start gap-2 min-w-0">
            <span
                class="d-inline-flex mt-1 flex-shrink-0"
                :class="props.entry.recoverable ? 'text-muted' : 'text-danger'"
            >
                <Icon name="alert" />
            </span>
            <div
                class="flex-fill min-w-0"
                style="min-width: 0;"
            >
                <CopyButton
                    class="float-end"
                    :value="copyText()"
                />
                <p
                    v-if="props.entry.exceptionClass"
                    class="font-monospace small text-danger text-break mb-1"
                >
                    {{ props.entry.exceptionClass }}
                </p>
                <p
                    class="mb-1"
                    style="overflow-wrap: break-word;"
                >
                    {{ props.entry.message }}
                </p>
                <JsonView
                    v-if="props.entry.responseBody"
                    :label="`Provider response${props.entry.responseStatus ? ` (${props.entry.responseStatus})` : ''}`"
                    :value="props.entry.responseBody"
                    test-id="error-response"
                    tone="error"
                />
                <div
                    v-if="props.entry.stackTrace"
                    class="mt-2"
                >
                    <Collapsible
                        label="Stack trace"
                        class="mt-2 w-100"
                    >
                        <pre
                            data-testid="stack-trace"
                            class="small overflow-auto p-2 border rounded text-break w-100"
                            style="max-height: 16rem; max-width: 100%; white-space: pre-wrap; overflow-wrap: break-word; word-break: break-all;"
                        >{{ props.entry.stackTrace }}</pre>
                    </Collapsible>
                </div>
            </div>
        </div>
    </div>
</template>
