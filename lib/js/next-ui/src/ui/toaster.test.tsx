import {describe, expect, it} from 'vitest';
import {toast} from 'sonner';
import './toaster';

function lastToast() {
    return toast.getHistory().at(-1) as {duration?: number};
}

describe('Toaster', () => {
    it('keeps error toasts until they are closed', () => {
        toast.error('Boom');

        expect(lastToast().duration).toBe(Infinity);
    });

    it('keeps an explicit error duration', () => {
        toast.error('Boom', {duration: 1000});

        expect(lastToast().duration).toBe(1000);
    });

    it('leaves the other toasts on the default duration', () => {
        toast.success('Saved');

        expect(lastToast().duration).toBeUndefined();
    });
});
