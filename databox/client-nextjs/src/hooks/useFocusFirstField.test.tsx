import {describe, expect, it} from 'vitest';
import {act, render} from '@testing-library/react';
import {useRef} from 'react';
import {useFocusFirstField} from './useFocusFirstField';

function Form({active}: {active: boolean}) {
    const ref = useRef<HTMLDivElement>(null);
    useFocusFirstField(ref, active);

    return (
        <div ref={ref}>
            <button type="button">not a field</button>
            <input type="hidden" />
            <input disabled aria-label="disabled" />
            <input aria-label="name" />
            <textarea aria-label="description" />
        </div>
    );
}

const nextFrame = () =>
    act(() => new Promise<void>(r => requestAnimationFrame(() => r())));

describe('useFocusFirstField', () => {
    it('focuses the first enabled field once active', async () => {
        const {rerender, getByLabelText} = render(<Form active={false} />);
        await nextFrame();
        expect(document.activeElement).toBe(document.body);

        rerender(<Form active />);
        await nextFrame();
        expect(document.activeElement).toBe(getByLabelText('name'));
    });

    it('leaves the focus alone when already inside', async () => {
        const {rerender, getByLabelText} = render(<Form active={false} />);
        getByLabelText('description').focus();
        rerender(<Form active />);
        await nextFrame();

        expect(document.activeElement).toBe(getByLabelText('description'));
    });
});
