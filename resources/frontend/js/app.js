import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import VueJsonPretty from 'vue-json-pretty';
import 'vue-json-pretty/lib/styles.css';
import Routes from './routes';
import AppShell from './App.vue';
import Alert from './components/Alert.vue';
import SchemeToggler from './components/SchemeToggler.vue';
import EmptyState from './components/EmptyState.vue';
import { spaBasePath } from './utils/api';
import { initTheme } from './composables/useTheme';

window.AiNexus = window.AiNexus || { path: 'ai_nexus', version: 'dev', streaming: true };

initTheme();

const router = createRouter({
    history: createWebHistory(spaBasePath()),
    routes: Routes,
});

const app = createApp(AppShell);

app.config.globalProperties.AiNexus = window.AiNexus;

app.use(router);

app.component('VueJsonPretty', VueJsonPretty);
app.component('Alert', Alert);
app.component('SchemeToggler', SchemeToggler);
app.component('EmptyState', EmptyState);

app.mount('#ai-nexus');
