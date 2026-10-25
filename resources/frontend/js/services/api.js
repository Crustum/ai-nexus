import api from '../utils/api';

export function toQuery(filters = {}) {
    const params = new URLSearchParams();

    if (filters.search) {
        params.set('search', filters.search);
    }

    for (const agent of filters.agents ?? []) {
        params.append('agents[]', agent);
    }

    for (const tool of filters.tools ?? []) {
        params.append('tools[]', tool);
    }

    if (filters.status) {
        params.set('status', filters.status);
    }

    if (filters.from) {
        params.set('from', filters.from);
    }

    if (filters.to) {
        params.set('to', filters.to);
    }

    if (filters.sort && filters.sort !== 'newest') {
        params.set('sort', filters.sort);
    }

    if (filters.page && filters.page > 1) {
        params.set('page', String(filters.page));
    }

    return params.toString();
}

export function getAgents(signal) {
    return api.get('/agents', { signal }).then((response) => response.data);
}

export function getAgent(slug, signal) {
    return api.get(`/agents/${encodeURIComponent(slug)}`, { signal }).then((response) => response.data);
}

export function getConversation(id, signal) {
    return api.get(`/conversations/${encodeURIComponent(id)}`, { signal }).then((response) => response.data);
}

export function deleteConversation(id) {
    return api.delete(`/conversations/${encodeURIComponent(id)}`).then(() => undefined);
}

export function getConversations(filters = {}, signal) {
    const query = toQuery(filters);

    return api.get(`/conversations${query ? `?${query}` : ''}`, { signal }).then((response) => response.data);
}

export function renameConversation(id, title) {
    return api.patch(`/conversations/${encodeURIComponent(id)}`, { title }).then((response) => response.data);
}

export function conversationAttachmentUrl(messageId, index) {
    return `${api.defaults.baseURL}/attachments/${encodeURIComponent(messageId)}/${index}`;
}
