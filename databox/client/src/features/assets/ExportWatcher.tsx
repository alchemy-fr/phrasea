'use client';

import {useExportStore} from './exportStore';
import {ExportStatus} from '@/types/api';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {exportChannel} from '@/lib/realtime/channels';
import {getAssetExport} from '@/lib/api/misc';

function ExportChannel({id}: {id: string}) {
    const update = useExportStore(s => s.update);
    const channel = exportChannel(id);

    useChannelEvent(channel, 'progress', (data: {progress: number}) =>
        update(id, {progress: data.progress, status: ExportStatus.InProgress})
    );
    // The event does not carry the signed URL of the archive
    useChannelEvent(channel, 'ready', () => {
        getAssetExport(id)
            .then(exp =>
                update(id, {
                    progress: 1,
                    status: ExportStatus.Ready,
                    downloadUrl: exp.downloadUrl,
                })
            )
            .catch(() => update(id, {status: ExportStatus.Failed}));
    });
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
