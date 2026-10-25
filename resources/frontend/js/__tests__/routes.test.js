import { describe, expect, it } from 'vitest';
import Routes from '../routes';

describe('routes', () => {
    it('exposes discovery, playground and history', () => {
        const paths = Routes.map((route) => route.path);

        expect(paths).toContain('/');
        expect(paths).toContain('/history');
        expect(paths).toContain('/playground/:agent?');
    });
});
