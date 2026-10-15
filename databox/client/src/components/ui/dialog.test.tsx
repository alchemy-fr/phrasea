import {describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render, screen} from '@testing-library/react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    KEEP_MODAL_OPEN_ATTR,
} from './dialog';

async function renderDialog() {
    const onOpenChange = vi.fn();
    render(
        <>
            <button type="button">outside</button>
            <button type="button" {...{[KEEP_MODAL_OPEN_ATTR]: ''}}>
                kept
            </button>
            <Dialog open onOpenChange={onOpenChange}>
                <DialogContent>
                    <DialogTitle>Title</DialogTitle>
                    <DialogDescription>Description</DialogDescription>
                </DialogContent>
            </Dialog>
        </>
    );
    // Radix listens to outside pointer downs from the next tick on.
    await act(() => new Promise(resolve => setTimeout(resolve)));

    return onOpenChange;
}

function clickOn(element: HTMLElement) {
    // A modal waits for the click that follows the pointer down to close.
    fireEvent.pointerDown(element);
    fireEvent.click(element);
}

describe('DialogContent', () => {
    it('closes on a click outside', async () => {
        const onOpenChange = await renderDialog();
        clickOn(screen.getByText('outside'));

        expect(onOpenChange).toHaveBeenCalledWith(false);
    });

    it('stays open on a click on a control kept above modals', async () => {
        const onOpenChange = await renderDialog();
        clickOn(screen.getByText('kept'));

        expect(onOpenChange).not.toHaveBeenCalled();
    });
});
