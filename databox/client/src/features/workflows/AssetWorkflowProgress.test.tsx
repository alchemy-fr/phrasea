import {beforeEach, describe, expect, it, vi} from 'vitest';
import {act, render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {Tooltip as TooltipPrimitive} from 'radix-ui';
import {createI18n} from '@/i18n';
import {
    JobStatus,
    WorkflowStatus,
    type Asset,
    type Workflow,
    type WorkflowDetail,
} from '@/types/api';
import {assetChannel, workflowChannel} from '@/lib/realtime/channels';
import {AssetWorkflowProgress} from './AssetWorkflowProgress';

vi.mock('next/link', () => ({
    default: ({href, children, scroll: _scroll, ...props}: any) => (
        <a href={typeof href === 'string' ? href : '#'} {...props}>
            {children}
        </a>
    ),
}));

const realtime = vi.hoisted(() => ({
    handlers: {} as Record<string, () => void>,
}));

vi.mock('@/lib/realtime/RealtimeProvider', () => ({
    useChannelEvent: (
        channel: string | undefined,
        event: string,
        callback: () => void
    ) => {
        if (channel) {
            realtime.handlers[`${channel}|${event}`] = callback;
        }
    },
}));

const api = vi.hoisted(() => ({
    getAssetWorkflows: vi.fn(),
    getWorkflow: vi.fn(),
}));

vi.mock('@/lib/api/misc', () => api);

const asset = (edit: boolean) =>
    ({id: 'a1', capabilities: {edit}}) as unknown as Asset;

const run = (status: WorkflowStatus): Workflow =>
    ({
        id: 'w1',
        name: 'asset-ingest:ws1',
        status,
        number: 2,
        startedAt: '2026-11-24T10:00:00Z',
        endedAt:
            status === WorkflowStatus.Started ? null : '2026-11-24T10:01:00Z',
    }) as Workflow;

const detail = (...statuses: (JobStatus | undefined)[]): WorkflowDetail => ({
    id: 'w1',
    name: 'asset-ingest:ws1',
    status: WorkflowStatus.Started,
    startedAt: '2026-11-24T10:00:00Z',
    stages: [
        {
            stage: 1,
            jobs: statuses.map((status, i) => ({
                jobId: `j${i}`,
                name: `Job ${i}`,
                status,
            })),
        },
    ],
});

function renderProgress(props: {asset: Asset; interactive?: boolean}) {
    const queryClient = new QueryClient({
        defaultOptions: {queries: {retry: false}},
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <I18nextProvider i18n={createI18n('en')}>
                <TooltipPrimitive.Provider>
                    <AssetWorkflowProgress {...props} />
                </TooltipPrimitive.Provider>
            </I18nextProvider>
        </QueryClientProvider>
    );
}

describe('AssetWorkflowProgress', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        realtime.handlers = {};
    });

    it('requests nothing without the edit capability', () => {
        const {container} = renderProgress({asset: asset(false)});

        expect(container.innerHTML).toBe('');
        expect(api.getAssetWorkflows).not.toHaveBeenCalled();
    });

    it('renders nothing on an asset without run', async () => {
        api.getAssetWorkflows.mockResolvedValue({items: []});
        const {container} = renderProgress({asset: asset(true)});

        await vi.waitFor(() =>
            expect(api.getAssetWorkflows).toHaveBeenCalledWith('a1')
        );
        expect(container.innerHTML).toBe('');
    });

    it('follows the jobs of the running run', async () => {
        api.getAssetWorkflows.mockResolvedValue({
            items: [run(WorkflowStatus.Started)],
        });
        api.getWorkflow.mockResolvedValue(
            detail(JobStatus.Success, JobStatus.Running, undefined)
        );
        renderProgress({asset: asset(true)});

        expect(await screen.findByText('1/3 jobs')).toBeTruthy();
        expect(screen.getByText('Running: Job 1')).toBeTruthy();
        expect(screen.getByText('Ingest #2')).toBeTruthy();
        expect(screen.getByRole('progressbar')).toBeTruthy();
        // Not interactive: no link to the run
        expect(screen.queryByRole('link')).toBeNull();

        api.getWorkflow.mockResolvedValue(
            detail(JobStatus.Success, JobStatus.Success, JobStatus.Running)
        );
        await act(async () =>
            realtime.handlers[`${workflowChannel('w1')}|job_update`]()
        );
        expect(await screen.findByText('2/3 jobs')).toBeTruthy();
        expect(screen.getByText('Running: Job 2')).toBeTruthy();
    });

    it('keeps the status of the run once it ended', async () => {
        api.getAssetWorkflows.mockResolvedValue({
            items: [run(WorkflowStatus.Started)],
        });
        api.getWorkflow.mockResolvedValue(detail(JobStatus.Running));
        renderProgress({asset: asset(true), interactive: true});
        await screen.findByText('0/1 jobs');

        api.getAssetWorkflows.mockResolvedValue({
            items: [run(WorkflowStatus.Success)],
        });
        await act(async () =>
            realtime.handlers[`${assetChannel('a1')}|workflow_update`]()
        );

        expect(await screen.findByText('Success')).toBeTruthy();
        expect(screen.queryByRole('progressbar')).toBeNull();
        expect(
            screen
                .getByRole('link', {name: 'Open the run'})
                .getAttribute('href')
        ).toBe('/workflows/w1');
    });
});
