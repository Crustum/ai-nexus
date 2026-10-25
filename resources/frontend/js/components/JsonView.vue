<script setup>
import hljs from 'highlight.js/lib/core';
import json from 'highlight.js/lib/languages/json';
import php from 'highlight.js/lib/languages/php';
import sql from 'highlight.js/lib/languages/sql';
import VueJsonPretty from 'vue-json-pretty';
import CopyButton from './CopyButton.vue';

hljs.registerLanguage('json', json);
hljs.registerLanguage('php', php);
hljs.registerLanguage('sql', sql);

const props = defineProps({
    label: {
        type: String,
        default: '',
    },
    value: {
        type: null,
        default: null,
    },
    testId: {
        type: String,
        default: null,
    },
    tone: {
        type: String,
        default: null,
    },
});

function text() {
    if (typeof props.value === 'string') {
        try {
            return JSON.stringify(JSON.parse(props.value), null, 2);
        } catch {
            return props.value;
        }
    }

    try {
        return JSON.stringify(props.value, null, 2);
    } catch {
        return String(props.value);
    }
}

function escapeHtml(source) {
    return String(source)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function detectLanguage(source) {
    const trimmed = source.trimStart();

    if (trimmed.startsWith('<?php') || trimmed.startsWith('<?=')) {
        return 'php';
    }

    if (/^(SELECT|INSERT|UPDATE|DELETE|CREATE|WITH|EXPLAIN)\b/i.test(trimmed)) {
        return 'sql';
    }

    try {
        JSON.parse(source);

        return 'json';
    } catch {
        return null;
    }
}

function codeHtml() {
    const source = text();
    const language = detectLanguage(source);

    if (!language) {
        return escapeHtml(source);
    }

    try {
        return hljs.highlight(source, { language }).value;
    } catch {
        return escapeHtml(source);
    }
}
</script>

<template>
    <div
        :data-testid="props.testId"
        class="json-view position-relative"
        :class="{ 'json-view-error': props.tone === 'error' }"
    >
        <p
            v-if="props.label"
            class="small text-muted mb-1"
        >
            {{ props.label }}
        </p>
        <CopyButton
            class="position-absolute top-0 end-0 mt-1 me-1"
            :value="text()"
        />
        <VueJsonPretty
            v-if="props.value !== null && typeof props.value === 'object'"
            :data="props.value"
        />
        <pre
            v-else
            class="json-plain small mb-0 overflow-auto"
            v-html="codeHtml()"
        />
    </div>
</template>

<style scoped>
/* Telescope-style: always-dark code box in both themes. */
.json-view :deep(.vjs-tree),
.json-plain {
    max-height: 18rem;
    overflow: auto;
    max-width: 100%;
    background: #292d3e;
    color: #bfc7d5;
    border: 1px solid #292d3e;
    border-radius: 0.5rem;
    padding: 0.75rem;
    padding-right: 2.5rem;
    font-size: 0.85em;
}

.json-view :deep(.vjs-tree) {
    background: #292d3e !important;
}

.json-plain {
    white-space: pre-wrap;
    overflow-wrap: break-word;
    word-break: break-all;
    margin-bottom: 0;
}

.json-view :deep(*:focus),
.json-view :deep(*:focus-visible) {
    outline: none;
}

.json-view-error :deep(.vjs-tree),
.json-view-error .json-plain {
    border-color: var(--bs-danger);
    color: #f1b0b0;
}
</style>
