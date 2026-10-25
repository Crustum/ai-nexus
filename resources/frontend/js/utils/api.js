import axios from 'axios';

function aiNexusBasePath() {
    const path = window.AiNexus?.path ?? 'ai_nexus';

    if (path === '' || path === '/') {
        return '';
    }

    return '/' + String(path).replace(/^\/+|\/+$/g, '');
}

export function apiBaseUrl() {
    return `${aiNexusBasePath()}/api`;
}

export function spaBasePath() {
    const base = aiNexusBasePath();

    return base === '' ? '/' : `${base}/`;
}

const token = document.head.querySelector('meta[name="csrf-token"]');

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

const api = axios.create({
    baseURL: apiBaseUrl(),
});

api.interceptors.request.use((config) => {
    config.baseURL = apiBaseUrl();

    return config;
});

export default api;
