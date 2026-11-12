import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {act, cleanup, render, waitFor} from '@testing-library/react';
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

describe('ExportWatcher', () => {
    beforeEach(() => {
        realtime.handlers = {};
        api.getAssetExport.mockReset();
        useExportStore.setState({
            exports: [{id: 'exp-1', status: ExportStatus.Pending}],
        });
        render(<ExportWatcher />);
    });

    afterEach(cleanup);

    it('fetches the download URL once the export is ready', async () => {
        api.getAssetExport.mockResolvedValue({
            id: 'exp-1',
            status: ExportStatus.Ready,
            downloadUrl: 'https://s3/archive.zip?signature',
        });

        act(() => realtime.handlers['private-export-exp-1|ready']({}));

        await waitFor(() =>
            expect(getExport()?.status).toBe(ExportStatus.Ready)
        );
        expect(api.getAssetExport).toHaveBeenCalledExactlyOnceWith('exp-1');
        expect(getExport()).toMatchObject({
            progress: 1,
            downloadUrl: 'https://s3/archive.zip?signature',
        });
    });

    it('fails the export when it cannot be fetched', async () => {
        api.getAssetExport.mockRejectedValue(new Error('403 Forbidden'));

        act(() => realtime.handlers['private-export-exp-1|ready']({}));

        await waitFor(() =>
            expect(getExport()?.status).toBe(ExportStatus.Failed)
        );
        expect(getExport()?.downloadUrl).toBeUndefined();
    });
});
