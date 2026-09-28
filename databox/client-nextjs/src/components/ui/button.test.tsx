import {describe, expect, it} from 'vitest';
import {act, fireEvent, render, screen} from '@testing-library/react';
import {Button} from './button';

function deferred() {
    let resolve!: () => void;
    const promise = new Promise<void>(r => (resolve = r));

    return {promise, resolve};
}

describe('Button', () => {
    it('shows a loader while the request of its click runs', async () => {
        const request = deferred();
        const {container} = render(
            <Button onClick={() => request.promise}>
                <svg data-testid="icon" /> Save
            </Button>
        );
        const button = screen.getByRole('button');

        fireEvent.click(button);
        expect(button).toHaveProperty('disabled', true);
        expect(container.querySelector('[data-slot=spinner]')).not.toBeNull();

        await act(async () => request.resolve());
        expect(button).toHaveProperty('disabled', false);
        expect(container.querySelector('[data-slot=spinner]')).toBeNull();
    });

    it('does not wait for a modal to close', () => {
        const handle = Object.assign(new Promise(() => {}), {
            id: 'modal',
            close: () => {},
        });
        render(<Button onClick={() => handle}>Open</Button>);
        const button = screen.getByRole('button');

        fireEvent.click(button);
        expect(button).toHaveProperty('disabled', false);
    });
});
