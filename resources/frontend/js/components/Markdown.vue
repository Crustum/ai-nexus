<script setup>
import { marked } from 'marked';

const props = defineProps({
    text: {
        type: String,
        default: '',
    },
});

function html() {
    return marked.parse((props.text ?? '').trim(), { breaks: true });
}
</script>

<template>
    <div
        class="markdown-body"
        v-html="html()"
    />
</template>

<style scoped>
.markdown-body {
    overflow-wrap: break-word;
    font-size: 0.875rem;
}

.markdown-body :deep(h1),
.markdown-body :deep(h2) {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.markdown-body :deep(p),
.markdown-body :deep(ul),
.markdown-body :deep(ol) {
    margin-bottom: 0.5rem;
}

.markdown-body :deep(ul),
.markdown-body :deep(ol) {
    padding-left: 1.25rem;
}

.markdown-body :deep(a) {
    color: var(--bs-link-color);
    text-decoration: underline;
}

.markdown-body :deep(a:hover) {
    color: var(--bs-link-hover-color);
}

.markdown-body :deep(code) {
    font-size: 0.85em;
    background: rgba(127, 127, 127, 0.18);
    border-radius: 0.25rem;
    padding: 0.1rem 0.3rem;
    overflow-wrap: break-word;
}

.markdown-body :deep(pre) {
    overflow: auto;
    max-width: 100%;
    padding: 0.75rem;
    border-radius: 0.5rem;
    background: rgba(127, 127, 127, 0.15);
}

.markdown-body :deep(pre code) {
    background: transparent;
    padding: 0;
}

.markdown-body :deep(blockquote) {
    border-left: 3px solid var(--bs-border-color);
    padding-left: 0.75rem;
    color: var(--bs-secondary-color);
    margin-bottom: 0.5rem;
}
</style>
