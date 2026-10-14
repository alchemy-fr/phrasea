'use client';

import {useEffect, useRef} from 'react';
import {useQueryClient, type QueryClient} from '@tanstack/react-query';
import type {Asset} from '@/types/api';
import {getAsset} from '@/lib/api/assets';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {useAssetStore} from '@/features/assets/assetStore';
import {
    rewriteItems,
    type StoryPages,
} from '@/features/assets/view/StoryCarousel';

/** Renditions come in bursts: the stories are refreshed once for all of them */
const refreshDelay = 1000;

/**
 * Refreshes what the stories show of their items (hover carousel of the list,
 * strip of the viewer, the story itself) once the renditions of these items
 * changed.
 *
 * The event does not tell which stories contain the asset: they are found
 * among the loaded data, the collections of the asset when it is displayed
 * and the items of the stories already browsed.
 */
export async function refreshStoriesOf(
    queryClient: QueryClient,
    assetIds: string[]
): Promise<void> {
    const {assets, reloadAsset} = useAssetStore.getState();
    const storyIds = new Set<string>();
    assetIds.forEach(id =>
        assets[id]?.collections?.forEach(c => {
            if (c.storyAsset) {
                storyIds.add(c.storyAsset.id);
            }
        })
    );

    const browsed = new Set<string>();
    queryClient
        .getQueriesData<StoryPages>({queryKey: ['story-assets']})
        .forEach(([key, data]) => {
            const storyId = key[1] as string | undefined;
            const items = data?.pages.flatMap(p => p.items) ?? [];
            assetIds.forEach(id => {
                if (storyId && items.some(a => a.id === id)) {
                    storyIds.add(storyId);
                    browsed.add(id);
                }
            });
        });

    await Promise.all(
        [...browsed].map(async id => {
            let asset: Asset;
            try {
                asset = await getAsset(id);
            } catch {
                // asset may have been deleted
                return;
            }
            // In place: refetching the story could bring a previous order back
            // (see `rewriteItems`).
            queryClient.setQueriesData<StoryPages>(
                {queryKey: ['story-assets']},
                data =>
                    rewriteItems(
                        data,
                        items => items.map(a => (a.id === id ? asset : a)),
                        0
                    )
            );
        })
    );

    storyIds.forEach(storyId => {
        void queryClient.invalidateQueries({
            queryKey: ['story-thumbnails', storyId],
        });
        void reloadAsset(storyId);
    });
}

export function useStoryRenditionRefresh(): void {
    const queryClient = useQueryClient();
    const pending = useRef(new Set<string>());
    const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => () => clearTimeout(timer.current), []);

    useChannelEvent('assets', 'rendition-update', (e: {assetId: string}) => {
        pending.current.add(e.assetId);
        if (timer.current) {
            return;
        }
        timer.current = setTimeout(() => {
            timer.current = undefined;
            const ids = [...pending.current];
            pending.current.clear();
            void refreshStoriesOf(queryClient, ids);
        }, refreshDelay);
    });
}
