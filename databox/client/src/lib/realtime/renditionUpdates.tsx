'use client';

import {useEffect, useMemo, useRef} from 'react';
import {create} from 'zustand';
import {useAssetStore} from '@/features/assets/assetStore';
import {useChannelEvent} from './RealtimeProvider';
import {RENDITION_UPDATE_EVENT, workspaceChannel} from './channels';

/**
 * The workspace channel only tells which asset changed: its readers may not
 * read that asset, the API applies the rights when it is reloaded.
 */
export type RenditionUpdate = {assetId: string};

type Listener = (event: RenditionUpdate) => void;

const listeners = new Set<Listener>();

function emit(event: RenditionUpdate): void {
    listeners.forEach(listener => listener(event));
}

/**
 * Calls back on every rendition update of the assets of the followed
 * workspaces: those of the assets displayed (see `useAssetStore`) and those
 * declared with `useWatchWorkspaces`.
 */
export function useRenditionUpdate(callback: Listener): void {
    const cbRef = useRef(callback);
    cbRef.current = callback;

    useEffect(() => {
        const listener: Listener = event => cbRef.current(event);
        listeners.add(listener);

        return () => {
            listeners.delete(listener);
        };
    }, []);
}

type WatchState = {
    counts: Record<string, number>;
    watch: (ids: string[]) => () => void;
};

const useWatchedWorkspaces = create<WatchState>(set => ({
    counts: {},
    watch: ids => {
        const change = (delta: number) =>
            set(s => {
                const counts = {...s.counts};
                ids.forEach(id => {
                    counts[id] = (counts[id] ?? 0) + delta;
                    if (counts[id] <= 0) {
                        delete counts[id];
                    }
                });

                return {counts};
            });
        change(1);

        return () => change(-1);
    },
}));

/** A sorted list of unique IDs, as a string to keep selectors stable */
function toKey(ids: (string | undefined)[]): string {
    return [...new Set(ids.filter((id): id is string => !!id))]
        .sort()
        .join(',');
}

function fromKey(key: string): string[] {
    return key ? key.split(',') : [];
}

/**
 * Follows the rendition updates of workspaces whose assets are not displayed
 * yet (e.g. the quarantine queue), for the lifetime of the component.
 */
export function useWatchWorkspaces(ids: (string | undefined)[]): void {
    const key = toKey(ids);
    const watch = useWatchedWorkspaces(s => s.watch);

    useEffect(() => {
        const watched = fromKey(key);
        if (watched.length === 0) {
            return;
        }

        return watch(watched);
    }, [key, watch]);
}

function WorkspaceFeed({workspaceId}: {workspaceId: string}) {
    useChannelEvent(
        workspaceChannel(workspaceId),
        RENDITION_UPDATE_EVENT,
        emit
    );

    return null;
}

/**
 * Subscribes to the channels of the followed workspaces and dispatches their
 * rendition updates to `useRenditionUpdate`. Mounted once, in the app shell:
 * the grid does not subscribe asset by asset, which would authorize as many
 * channels.
 */
export function RenditionUpdateFeed() {
    const storeKey = useAssetStore(s =>
        toKey(Object.values(s.assets).map(a => a.workspace?.id))
    );
    const watchedKey = useWatchedWorkspaces(s => toKey(Object.keys(s.counts)));
    const workspaceIds = useMemo(
        () => fromKey(toKey([...fromKey(storeKey), ...fromKey(watchedKey)])),
        [storeKey, watchedKey]
    );

    return (
        <>
            {workspaceIds.map(id => (
                <WorkspaceFeed key={id} workspaceId={id} />
            ))}
        </>
    );
}
