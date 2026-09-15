import {describe, expect, it, vi, beforeEach} from 'vitest';
import {act, render, screen} from '@testing-library/react';
import {ModalProvider, useModals, type ModalProps} from './ModalProvider';

let pathname = '/assets';
vi.mock('next/navigation', () => ({
    usePathname: () => pathname,
}));

type Api = ReturnType<typeof useModals>;

function Probe({onReady}: {onReady: (api: Api) => void}) {
    onReady(useModals());

    return null;
}

function Sample({
    open,
    resolve,
    label = 'sample',
}: ModalProps<string> & {label?: string}) {
    if (!open) {
        return null;
    }

    return (
        <button onClick={() => resolve?.('done')} data-testid={label}>
            {label}
        </button>
    );
}

function setup() {
    let api!: Api;
    const result = render(
        <ModalProvider>
            <Probe onReady={a => (api = a)} />
        </ModalProvider>
    );

    return {
        get api() {
            return api;
        },
        ...result,
    };
}

beforeEach(() => {
    pathname = '/assets';
});

describe('ModalProvider', () => {
    it('resolves the caller with the modal result', async () => {
        const t = setup();
        let handle!: ReturnType<Api['openModal']>;
        act(() => {
            handle = t.api.openModal(Sample, {});
        });
        act(() => {
            screen.getByTestId('sample').click();
        });

        await expect(handle).resolves.toBe('done');
        expect(screen.queryByTestId('sample')).toBeNull();
    });

    it('resolves undefined when the modal is dismissed', async () => {
        const t = setup();
        let handle!: ReturnType<Api['openModal']>;
        act(() => {
            handle = t.api.openModal(Sample, {});
        });
        act(() => {
            handle.close();
        });

        await expect(handle).resolves.toBeUndefined();
    });

    it('does not stack two modals sharing a key', () => {
        const t = setup();
        act(() => {
            t.api.openModal(Sample, {label: 'first'}, {key: 'k'});
            t.api.openModal(Sample, {label: 'second'}, {key: 'k'});
        });

        expect(screen.queryByTestId('first')).toBeNull();
        expect(screen.getByTestId('second')).toBeTruthy();
        expect(t.api.count).toBe(1);
    });

    it('closes open modals when the route changes', async () => {
        const t = setup();
        let closed!: ReturnType<Api['openModal']>;
        let kept!: ReturnType<Api['openModal']>;
        act(() => {
            closed = t.api.openModal(Sample, {label: 'closed'});
            kept = t.api.openModal(
                Sample,
                {label: 'kept'},
                {keepOnNavigate: true}
            );
        });

        pathname = '/collections';
        act(() => {
            t.rerender(
                <ModalProvider>
                    <Probe onReady={() => {}} />
                </ModalProvider>
            );
        });

        await expect(closed).resolves.toBeUndefined();
        expect(screen.getByTestId('kept')).toBeTruthy();
        void kept;
    });
});
