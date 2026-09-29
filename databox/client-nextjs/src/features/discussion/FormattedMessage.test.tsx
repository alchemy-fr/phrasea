import {describe, expect, it} from 'vitest';
import {render} from '@testing-library/react';
import {FormattedMessage} from './FormattedMessage';

describe('FormattedMessage', () => {
    it('renders mentions as chips, the current user highlighted', () => {
        const {container} = render(
            <FormattedMessage
                content="Hi @[john](1) and @[me](2)"
                currentUserId="2"
            />
        );
        const chips = container.querySelectorAll('[data-mention]');
        expect([...chips].map(c => c.textContent)).toEqual(['@john', '@me']);
        expect(chips[0].className).toContain('bg-primary/15');
        expect(chips[1].className).toContain('text-primary-foreground');
    });

    it('renders formatting as elements, never raw HTML', () => {
        const {container} = render(
            <FormattedMessage
                content={'**b** <img src=x onerror=alert(1)>\n- i'}
            />
        );
        expect(container.querySelector('strong')?.textContent).toBe('b');
        expect(container.querySelector('img')).toBeNull();
        expect(container.querySelector('li')?.textContent).toBe('i');
        expect(container.textContent).toContain('<img src=x onerror=alert(1)>');
    });
});
