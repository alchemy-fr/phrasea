'use client';

import {useEffect} from 'react';
import {useExportStore} from './exportStore';
import {AssetExport, ExportStatus} from '@/types/api';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {exportChannel} from '@/lib/realtime/channels';
import {getAssetExport} from '@/lib/api/misc';
import {isApiError} from '@/lib/api/http';

/**
 * The export is also polled: realtime events can be lost (channel refused,
 * realtime disabled, export finished before the subscription was authorized)
 */
const POLL_FIRST_DELAY_MS = 2_000;
const POLL_INTERVAL_MS = 5_000;

type UpdateExport = (id: string, patch: Partial<AssetExport>) => void;

/**
 * Applies the export read from the API, once it is finished.
 * Progress only comes from realtime and is never overwritten here.
 *
 * @returns whether the export is finished
 */
function applyFetchedExport(
    update: UpdateExport,
    id: string,
    exp: AssetExport
): boolean {
    if (exp.status === ExportStatus.Ready) {
        update(id, {
            progress: 1,
            status: ExportStatus.Ready,
            downloadUrl: exp.downloadUrl,
        });

        return true;
    }
    if (exp.status === ExportStatus.Failed) {
        update(id, {status: ExportStatus.Failed, error: exp.error});

        return true;
    }

    return false;
}

function ExportChannel({id}: {id: string}) {
    const update = useExportStore(s => s.update);
    const channel = exportChannel(id);

    useChannelEvent(channel, 'progress', (data: {progress: number}) =>
        update(id, {progress: data.progress, status: ExportStatus.InProgress})
    );
    // The event does not carry the signed URL of the archive
    useChannelEvent(channel, 'ready', () => {
        getAssetExport(id)
            .then(exp => applyFetchedExport(update, id, exp))
            .catch(() => update(id, {status: ExportStatus.Failed}));
    });
    useChannelEvent(channel, 'error', (data: {error?: string}) =>
        update(id, {status: ExportStatus.Failed, error: data.error})
    );

    // Unmounted by ExportWatcher as soon as the export is ready or failed
    useEffect(() => {
        let cancelled = false;
        let timer: ReturnType<typeof setTimeout> | undefined;

        const poll = async () => {
            try {
                const exp = await getAssetExport(id);
                if (!cancelled && applyFetchedExport(update, id, exp)) {
                    return;
                }
            } catch (e) {
                // Not readable any more; other errors (network, 5xx) are retried
                if (!cancelled && (isApiError(e, 403) || isApiError(e, 404))) {
                    update(id, {status: ExportStatus.Failed});

                    return;
                }
            }
            if (!cancelled) {
                timer = setTimeout(poll, POLL_INTERVAL_MS);
            }
        };

        timer = setTimeout(poll, POLL_FIRST_DELAY_MS);

        return () => {
            cancelled = true;
            clearTimeout(timer);
        };
    }, [id, update]);

    return null;
}

/** Follows every tracked export, through realtime and polling */
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
