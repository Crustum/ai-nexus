import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import Icon from '../components/Icon.vue';

describe('Icon', () => {
    it('updates the glyph when the name changes', async () => {
        const wrapper = mount(Icon, { props: { name: 'sun' } });

        expect(wrapper.attributes('viewBox')).toBe('0 0 20 20');

        await wrapper.setProps({ name: 'moon' });

        expect(wrapper.attributes('viewBox')).toBe('0 0 24 24');
        expect(wrapper.html()).toContain('M12 3a6 6 0 0 0 9 9');
    });

    it('falls back for unknown names', () => {
        const wrapper = mount(Icon, { props: { name: 'nope' } });

        expect(wrapper.html()).toContain('<circle');
    });
});
