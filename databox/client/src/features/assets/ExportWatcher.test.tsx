import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {act, cleanup, render} from '@testing-library/react';
import {ApiError} from '@/lib/api/http';
import {ExportStatus} from '@/types/api';
import {useExportStore} from './exportStore';
import {ExportWatcher} from './ExportWatcher';

const realtime = vi.hoisted(() => ({
    handlers: {} as Record<string, (data: unknown) => void>,
}));

vi.mock('@/lib/realtime/RealtimeProvider', () => ({
    useChannelEvent: (
        channel: string,
        event: string,
        callback: (data: unknown) => void
    ) => {
        realtime.handlers[`${channel}|${event}`] = callback;
    },
}));

const api = vi.hoisted(() => ({getAssetExport: vi.fn()}));

vi.mock('@/lib/api/misc', () => api);

function getExport() {
    return useExportStore.getState().exports.find(e => e.id === 'exp-1');
}

/** Lets the timers run and the pending requests settle */
async function advance(ms: number) {
    await act(() => vi.advanceTimersByTimeAsync(ms));
}

const readyExport = {
    id: 'exp-1',
    status: ExportStatus.Ready,
    downloadUrl: 'https://s3/archive.zip?signature',
};

describe('ExportWatcher', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        realtime.handlers = {};
        api.getAssetExport.mockReset();
        useExportStore.setState({
            exports: [{id: 'exp-1', status: ExportStatus.Pending}],
        });
        render(<ExportWatcher />);
    });

    afterEach(() => {
        cleanup();
        vi.useRealTimers();
    });

    describe('realtime', () => {
        it('fetches the download URL once the export is ready', async () => {
            api.getAssetExport.mockResolvedValue(readyExport);

            act(() => realtime.handlers['private-export-exp-1|ready']({}));
            await advance(0);

            expect(getExport()?.status).toBe(ExportStatus.Ready);
            expect(api.getAssetExport).toHaveBeenCalledExactlyOnceWith('exp-1');
            expect(getExport()).toMatchObject({
                progress: 1,
                downloadUrl: 'https://s3/archive.zip?signature',
            });
        });

        it('fails the export when it cannot be fetched', async () => {
            api.getAssetExport.mockRejectedValue(new Error('403 Forbidden'));

            act(() => realtime.handlers['private-export-exp-1|ready']({}));
            await advance(0);

            expect(getExport()?.status).toBe(ExportStatus.Failed);
            expect(getExport()?.downloadUrl).toBeUndefined();
        });
    });

    describe('polling', () => {
        it('finishes the export without any realtime event', async () => {
            api.getAssetExport.mockResolvedValue(readyExport);

            await advance(1_000);
            expect(api.getAssetExport).not.toHaveBeenCalled();

            await advance(1_000);

            expect(api.getAssetExport).toHaveBeenCalledExactlyOnceWith('exp-1');
            expect(getExport()).toMatchObject({
                status: ExportStatus.Ready,
                progress: 1,
                downloadUrl: 'https://s3/archive.zip?signature',
            });
        });

        it('fails the export reported as failed', async () => {
            api.getAssetExport.mockResolvedValue({
                id: 'exp-1',
                status: ExportStatus.Failed,
                error: 'Boom',
            });

            await advance(2_000);

            expect(getExport()).toMatchObject({
                status: ExportStatus.Failed,
                error: 'Boom',
            });
        });

        it('keeps following an export in progress, with its realtime progress', async () => {
            api.getAssetExport.mockResolvedValue({
                id: 'exp-1',
                status: ExportStatus.InProgress,
            });

            act(() =>
                realtime.handlers['private-export-exp-1|progress']({
                    progress: 0.4,
                })
            );
            await advance(2_000);

            expect(api.getAssetExport).toHaveBeenCalledTimes(1);
            expect(getExport()).toMatchObject({
                status: ExportStatus.InProgress,
                progress: 0.4,
            });

            api.getAssetExport.mockResolvedValue(readyExport);
            await advance(4_000);
            expect(api.getAssetExport).toHaveBeenCalledTimes(1);

            await advance(1_000);

            expect(api.getAssetExport).toHaveBeenCalledTimes(2);
            expect(getExport()?.status).toBe(ExportStatus.Ready);
        });

        it('retries after a network error', async () => {
            api.getAssetExport.mockRejectedValueOnce(
                new Error('Network error')
            );
            api.getAssetExport.mockResolvedValueOnce(readyExport);

            await advance(2_000);

            expect(api.getAssetExport).toHaveBeenCalledTimes(1);
            expect(getExport()?.status).toBe(ExportStatus.Pending);

            await advance(5_000);

            expect(api.getAssetExport).toHaveBeenCalledTimes(2);
            expect(getExport()?.status).toBe(ExportStatus.Ready);
        });

        it('fails the export which is not readable any more', async () => {
            api.getAssetExport.mockRejectedValue(
                new ApiError('Not Found', 404, {})
            );

            await advance(2_000);

            expect(getExport()?.status).toBe(ExportStatus.Failed);

            await advance(20_000);

            expect(api.getAssetExport).toHaveBeenCalledTimes(1);
        });

        it('stops polling once the export is ready', async () => {
            api.getAssetExport.mockResolvedValue(readyExport);

            await advance(2_000);
            expect(getExport()?.status).toBe(ExportStatus.Ready);

            await advance(60_000);

            expect(api.getAssetExport).toHaveBeenCalledTimes(1);
        });

        it('stops polling once realtime reports the export ready', async () => {
            api.getAssetExport.mockResolvedValue(readyExport);

            act(() => realtime.handlers['private-export-exp-1|ready']({}));
            await advance(0);
            expect(getExport()?.status).toBe(ExportStatus.Ready);

            await advance(60_000);

            expect(api.getAssetExport).toHaveBeenCalledTimes(1);
        });
    });
});
