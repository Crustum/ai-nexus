import { describe, expect, it } from 'vitest';
import { resolveIsDark } from '../composables/useTheme';

describe('resolveIsDark', () => {
    it('resolves explicit themes', () => {
        expect(resolveIsDark('dark')).toBe(true);
        expect(resolveIsDark('light')).toBe(false);
    });
});
