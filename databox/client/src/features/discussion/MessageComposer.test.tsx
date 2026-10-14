import {createRef, ReactNode} from 'react';
import {describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render, screen, waitFor} from '@testing-library/react';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {Tooltip as TooltipPrimitive} from 'radix-ui';
import {MessageComposer, MessageComposerHandle} from './MessageComposer';

vi.mock('@/lib/api/upload', () => ({multipartUpload: vi.fn()}));

// Not implemented by jsdom
Element.prototype.scrollIntoView ??= vi.fn();
// Read by ProseMirror when it scrolls the selection into view
const emptyRects = () =>
    Object.assign([], {item: () => null}) as unknown as DOMRectList;
Range.prototype.getClientRects ??= emptyRects;
Range.prototype.getBoundingClientRect ??= () => new DOMRect();

function wrapper({children}: {children: ReactNode}) {
    return (
        <QueryClientProvider client={new QueryClient()}>
            <TooltipPrimitive.Provider>{children}</TooltipPrimitive.Provider>
        </QueryClientProvider>
    );
}

async function setup(props: Partial<Parameters<typeof MessageComposer>[0]>) {
    const ref = createRef<MessageComposerHandle>();
    const onSubmit = vi.fn();
    const onChange = vi.fn();
    const utils = render(
        <MessageComposer
            ref={ref}
            onSubmit={onSubmit}
            onChange={onChange}
            placeholder="Write"
            {...props}
        />,
        {wrapper}
    );
    await waitFor(() =>
        expect(utils.container.querySelector('.ProseMirror')).not.toBeNull()
    );

    return {...utils, ref, onSubmit, onChange};
}

describe('MessageComposer', () => {
    it('toggles the formatting toolbar', async () => {
        await setup({});
        expect(screen.getByRole('toolbar')).toBeTruthy();
        fireEvent.click(screen.getByLabelText('Hide formatting'));
        expect(screen.queryByRole('toolbar')).toBeNull();
        fireEvent.click(screen.getByLabelText('Show formatting'));
        expect(screen.getByRole('toolbar')).toBeTruthy();
    });

    it('sends the quoted reply as markup', async () => {
        const {ref, onSubmit} = await setup({});
        const send = screen.getByLabelText('Send') as HTMLButtonElement;
        expect(send.disabled).toBe(true);

        act(() => ref.current!.quote('**hello**\nworld'));
        act(() => ref.current!.mention({id: 'u1', username: 'john'}));
        await waitFor(() => expect(send.disabled).toBe(false));
        fireEvent.click(send);
        expect(onSubmit).toHaveBeenCalledWith(
            '> **hello**\n> world\n@[john](u1)',
            []
        );
    });

    it('edits a message in place of the draft', async () => {
        const onSaveEdit = vi.fn();
        const onCancelEdit = vi.fn();
        const {ref, rerender, container} = await setup({});
        act(() => ref.current!.mention({id: 'u1', username: 'john'}));

        const props = {onSubmit: vi.fn(), onSaveEdit, onCancelEdit};
        rerender(
            <MessageComposer
                ref={ref}
                {...props}
                editing={{id: 'm1', content: 'old _text_'}}
            />
        );
        await waitFor(() =>
            expect(container.querySelector('.ProseMirror em')).not.toBeNull()
        );
        expect(screen.getByText('Editing message')).toBeTruthy();
        expect(screen.getByLabelText('Attach files')).toHaveProperty(
            'disabled',
            true
        );

        fireEvent.click(screen.getByLabelText('Save'));
        expect(onSaveEdit).toHaveBeenCalledWith('old _text_');

        fireEvent.click(screen.getByLabelText('Cancel editing'));
        expect(onCancelEdit).toHaveBeenCalled();

        // The draft comes back once the edition is over
        rerender(<MessageComposer ref={ref} {...props} editing={null} />);
        await waitFor(() =>
            expect(
                container.querySelector('.ProseMirror [data-mention="john"]')
            ).not.toBeNull()
        );
        expect(screen.queryByText('Editing message')).toBeNull();
    });
});
