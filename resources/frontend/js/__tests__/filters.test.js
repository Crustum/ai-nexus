import { describe, expect, it } from 'vitest';
import { activeFilterCount, emptyFilters, fromParams, toParams } from '../utils/filters';

describe('filters', () => {
    it('starts empty', () => {
        expect(activeFilterCount(emptyFilters())).toBe(0);
    });

    it('counts set filters', () => {
        expect(activeFilterCount({ ...emptyFilters(), search: 'hoodie', status: 'error' })).toBe(2);
        expect(activeFilterCount({ ...emptyFilters(), agents: ['a', 'b'], tools: ['t'] })).toBe(3);
    });

    it('round-trips through the URL', () => {
        const filters = {
            ...emptyFilters(),
            search: 'hoodie',
            agents: ['a', 'b'],
            tools: ['t'],
            status: 'error',
            sort: 'oldest',
            page: 2,
        };
        const restored = fromParams(toParams(filters));

        expect(restored.search).toBe('hoodie');
        expect(restored.agents).toEqual(['a', 'b']);
        expect(restored.tools).toEqual(['t']);
        expect(restored.status).toBe('error');
        expect(restored.sort).toBe('oldest');
        expect(restored.page).toBe(2);
    });

    it('omits defaults from the URL', () => {
        expect(toParams(emptyFilters())).toEqual({});
    });
});
