import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {createI18n} from '@/i18n';
import type {Asset, QuarantineBatchPrepareItem} from '@/types/api';
import {AssetStatus} from '@/types/api';
import {QuarantineBatchDialog} from './QuarantineBatchDialog';
import {useQuarantineQueueStore} from './quarantineQueue';

vi.mock('next/navigation', () => ({
    usePathname: () => '/quarantine',
    useSearchParams: () => new URLSearchParams(),
    useRouter: () => ({push: vi.fn(), replace: vi.fn()}),
}));

const {api} = vi.hoisted(() => ({
    api: {
        runQuarantineBatch: vi.fn(),
    },
}));

vi.mock('@/lib/api/assets', async importOriginal => {
    const actual = await importOriginal<typeof import('@/lib/api/assets')>();

    return {...actual, ...api};
});

// jsdom implements neither of these, and Radix's dialog needs both
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof ResizeObserver;
Element.prototype.scrollIntoView ??= () => undefined;

function asset(id: string, status = AssetStatus.Quarantined): Asset {
    return {
        'id': id,
        '@id': `/assets/${id}`,
        'name': `Asset ${id}`,
        'status': status,
        'createdAt': '2026-01-01T10:00:00+00:00',
        'capabilities': {},
        'attributes': [],
        'tags': [],
        'collections': [],
        'workspace': {id: 'ws', name: 'Workspace'},
    } as unknown as Asset;
}

function item(
    id: string,
    eligible: Partial<QuarantineBatchPrepareItem>,
    targets: string[] = []
): QuarantineBatchPrepareItem {
    return {
        id,
        bypass: false,
        trash: false,
        delete: false,
        mergeAsVersion: false,
        mergeDiscard: false,
        reasons: {},
        duplicates: targets.map(t => ({
            asset: asset(t, AssetStatus.Accepted),
            analyzers: ['checksum'],
            validTarget: true,
        })),
        ...eligible,
    };
}

function renderDialog(
    props: Partial<Parameters<typeof QuarantineBatchDialog>[0]> &
        Pick<
            Parameters<typeof QuarantineBatchDialog>[0],
            'action' | 'assets' | 'eligibility'
        >
) {
    const queryClient = new QueryClient({
        defaultOptions: {queries: {retry: false}},
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <I18nextProvider i18n={createI18n('en')}>
                <QuarantineBatchDialog open onOpenChange={vi.fn()} {...props} />
            </I18nextProvider>
        </QueryClientProvider>
    );
}

const summary = () =>
    screen.getByTestId('quarantine-batch-summary').textContent;

describe('QuarantineBatchDialog', () => {
    beforeEach(() => {
        useQuarantineQueueStore.getState().reset();
        vi.clearAllMocks();
    });

    it('tells how many assets will be processed and why the others are ignored', () => {
        renderDialog({
            action: 'trash',
            assets: [asset('q-1'), asset('q-2'), asset('q-3')],
            eligibility: new Map([
                ['q-1', item('q-1', {trash: true})],
                ['q-2', item('q-2', {trash: true})],
                ['q-3', item('q-3', {reasons: {trash: 'forbidden'}})],
            ]),
        });

        expect(summary()).toBe('2 assets will be processed, 1 ignored');
        const ignored = screen.getByTestId('quarantine-batch-ignored');
        expect(ignored.textContent).toContain('Asset q-3');
        expect(ignored.textContent).toContain('Not allowed');
    });

    it('merges once every target is chosen, then reports the outcome', async () => {
        const onComplete = vi.fn();
        api.runQuarantineBatch.mockResolvedValue({
            results: [
                {id: 'q-1', status: 'processed'},
                {id: 'q-2', status: 'skipped', reason: 'not_quarantined'},
                {id: 'q-4', status: 'failed', reason: 'error'},
            ],
            processed: 1,
            skipped: 1,
            failed: 1,
        });

        renderDialog({
            action: 'merge',
            assets: [asset('q-1'), asset('q-2'), asset('q-3'), asset('q-4')],
            eligibility: new Map([
                [
                    'q-1',
                    item('q-1', {mergeAsVersion: true, mergeDiscard: true}, [
                        't-a',
                        't-b',
                    ]),
                ],
                [
                    'q-2',
                    item('q-2', {mergeAsVersion: true, mergeDiscard: true}, [
                        't-c',
                    ]),
                ],
                [
                    'q-3',
                    item('q-3', {
                        mergeDiscard: true,
                        reasons: {mergeAsVersion: 'no_valid_target'},
                    }),
                ],
                [
                    'q-4',
                    item('q-4', {mergeAsVersion: true, mergeDiscard: true}, [
                        't-d',
                    ]),
                ],
            ]),
            onComplete,
        });

        // q-1 has two possible targets: it is not ready until one is chosen
        expect(summary()).toBe(
            '2 assets will be processed, 1 ignored, 1 waiting for its target'
        );
        const target = screen.getByTestId('quarantine-batch-target');
        expect(target.textContent).toContain('Asset q-1');
        fireEvent.click(target.querySelector('[role="combobox"]')!);
        fireEvent.click(await screen.findByRole('option', {name: 'Asset t-b'}));
        expect(summary()).toBe('3 assets will be processed, 1 ignored');

        fireEvent.click(screen.getByRole('button', {name: 'Merge duplicates'}));

        const report = await screen.findByTestId('quarantine-batch-report');
        expect(api.runQuarantineBatch).toHaveBeenCalledWith({
            action: 'merge',
            addAsVersion: true,
            items: [{id: 'q-1', targetId: 't-b'}, {id: 'q-2'}, {id: 'q-4'}],
        });
        expect(report.textContent).toContain('1 processed');
        // q-2 changed since the selection, q-3 was left out
        expect(report.textContent).toContain('2 ignored');
        expect(report.textContent).toContain('1 failed');
        const lines = screen
            .getAllByTestId('quarantine-batch-report-item')
            .map(li => li.textContent);
        expect(lines).toEqual([
            'Asset q-4Error',
            'Asset q-2No longer in quarantine',
            expect.stringContaining('Asset q-3'),
        ]);

        // Only the processed asset leaves the queue
        expect(useQuarantineQueueStore.getState().resolved).toEqual(['q-1']);
        expect(onComplete).toHaveBeenCalledWith(['q-1']);
    });

    it('asks to type the confirmation word before a permanent deletion', async () => {
        api.runQuarantineBatch.mockResolvedValue({
            results: [{id: 'q-1', status: 'processed'}],
            processed: 1,
            skipped: 0,
            failed: 0,
        });
        renderDialog({
            action: 'delete',
            assets: [asset('q-1')],
            eligibility: new Map([['q-1', item('q-1', {delete: true})]]),
        });

        const submit = screen.getByRole('button', {
            name: 'Delete permanently',
        }) as HTMLButtonElement;
        expect(submit.disabled).toBe(true);
        fireEvent.change(screen.getByRole('textbox'), {
            target: {value: 'Delete'},
        });
        expect(submit.disabled).toBe(false);
        fireEvent.click(submit);

        await waitFor(() =>
            expect(useQuarantineQueueStore.getState().resolved).toEqual(['q-1'])
        );
        expect(api.runQuarantineBatch).toHaveBeenCalledWith({
            action: 'delete',
            items: [{id: 'q-1'}],
        });
    });
});
