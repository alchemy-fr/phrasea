'use client';

import {useExportStore} from './exportStore';
import {ExportStatus} from '@/types/api';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';

function ExportChannel({id}: {id: string}) {
    const update = useExportStore(s => s.update);
    const channel = `export-${id}`;

    useChannelEvent(channel, 'progress', (data: {progress: number}) =>
        update(id, {progress: data.progress, status: ExportStatus.InProgress})
    );
    useChannelEvent(channel, 'ready', (data: {downloadUrl: string}) =>
        update(id, {
            progress: 1,
            status: ExportStatus.Ready,
            downloadUrl: data.downloadUrl,
        })
    );
    useChannelEvent(channel, 'error', (data: {error?: string}) =>
        update(id, {status: ExportStatus.Failed, error: data.error})
    );

    return null;
}

/** Subscribes to realtime progress of every tracked export */
export function ExportWatcher() {
    const exportsData = useExportStore(s => s.exports);

    return (
        <>
            {exportsData
                .filter(
                    e =>
                        e.status !== ExportStatus.Ready &&
                        e.status !== ExportStatus.Failed
                )
                .map(e => (
                    <ExportChannel key={e.id} id={e.id} />
                ))}
        </>
    );
}
