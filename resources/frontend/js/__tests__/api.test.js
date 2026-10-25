import { describe, expect, it } from 'vitest';
import { toQuery } from '../services/api';

describe('toQuery', () => {
    it('omits empty filters', () => {
        expect(toQuery({})).toBe('');
    });

    it('encodes search, status, sort and page', () => {
        expect(toQuery({ search: 'hoodie', status: 'error', sort: 'oldest', page: 2 })).toBe(
            'search=hoodie&status=error&sort=oldest&page=2',
        );
    });

    it('omits default sort and first page', () => {
        expect(toQuery({ sort: 'newest', page: 1 })).toBe('');
    });

    it('appends repeated agents and tools', () => {
        const query = toQuery({ agents: ['a', 'b'], tools: ['t'] });

        expect(query).toContain('agents%5B%5D=a');
        expect(query).toContain('agents%5B%5D=b');
        expect(query).toContain('tools%5B%5D=t');
    });
});
