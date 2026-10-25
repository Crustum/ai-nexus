export function emptyFilters() {
    return {
        search: '',
        agents: [],
        status: null,
        tools: [],
        from: null,
        to: null,
        sort: 'newest',
        page: 1,
    };
}

export function activeFilterCount(filters) {
    let count = 0;

    if (filters.search) {
        count += 1;
    }

    count += (filters.agents ?? []).length;
    count += (filters.tools ?? []).length;

    if (filters.status) {
        count += 1;
    }

    if (filters.from) {
        count += 1;
    }

    if (filters.to) {
        count += 1;
    }

    return count;
}

function toList(value) {
    if (Array.isArray(value)) {
        return value
            .filter((item) => item !== null && item !== undefined && item !== '')
            .map(String);
    }

    if (value === null || value === undefined || value === '') {
        return [];
    }

    return [String(value)];
}

function pickList(query, ...keys) {
    // Also accepts URLSearchParams (legacy callers/tests).
    if (query && typeof query.getAll === 'function') {
        const out = [];

        for (const key of keys) {
            out.push(...query.getAll(key));
        }

        return out.filter((item) => item !== '');
    }

    const out = [];

    for (const key of keys) {
        out.push(...toList(query?.[key]));
    }

    return [...new Set(out)];
}

function pickOne(query, key, fallback = null) {
    if (query && typeof query.get === 'function') {
        return query.get(key) ?? fallback;
    }

    const value = query?.[key];

    if (Array.isArray(value)) {
        return value.length > 0 ? String(value[value.length - 1]) : fallback;
    }

    return value ?? fallback;
}

export function fromParams(query) {
    return {
        search: pickOne(query, 'search', '') ?? '',
        agents: pickList(query, 'agents[]', 'agents'),
        status: pickOne(query, 'status', null) || null,
        tools: pickList(query, 'tools[]', 'tools'),
        from: pickOne(query, 'from', null) || null,
        to: pickOne(query, 'to', null) || null,
        sort: pickOne(query, 'sort') === 'oldest' ? 'oldest' : 'newest',
        page: Number(pickOne(query, 'page', 1)) || 1,
    };
}

export function toParams(filters) {
    // Plain object with real arrays — vue-router repeats the key per value,
    // so multiselect survives the round-trip (Object.fromEntries on
    // URLSearchParams used to keep only the last value). Bracket keys match
    // the backend convention used by services/api.js.
    const query = {};

    if (filters.search) {
        query.search = filters.search;
    }

    if ((filters.agents ?? []).length > 0) {
        query['agents[]'] = [...filters.agents];
    }

    if ((filters.tools ?? []).length > 0) {
        query['tools[]'] = [...filters.tools];
    }

    if (filters.status) {
        query.status = filters.status;
    }

    if (filters.from) {
        query.from = filters.from;
    }

    if (filters.to) {
        query.to = filters.to;
    }

    if (filters.sort === 'oldest') {
        query.sort = 'oldest';
    }

    if (filters.page > 1) {
        query.page = String(filters.page);
    }

    return query;
}
