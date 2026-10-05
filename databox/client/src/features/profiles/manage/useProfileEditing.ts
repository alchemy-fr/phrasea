'use client';

import {useMemo} from 'react';
import {useQueryClient} from '@tanstack/react-query';
import {toast} from 'sonner';
import type {
    DisplayProfile,
    ProfileItem,
    ProfileItemSection,
} from '@/types/api';
import {
    addToProfile,
    putProfileItem,
    removeFromProfile,
    sortProfileItems,
} from '@/lib/api/misc';
import {useProfileStore} from '../profileStore';

export const profileQueryKey = (id: string) => ['profile', id];

/**
 * The requests of each profile, run one after the other: a reorder listing
 * an item must reach the API before the removal of that item.
 */
const queues = new Map<string, Promise<unknown>>();

function enqueue<T>(profileId: string, task: () => Promise<T>): Promise<T> {
    const next = (queues.get(profileId) ?? Promise.resolve())
        .catch(() => undefined)
        .then(task);
    queues.set(profileId, next);

    return next;
}

/**
 * The edits of a profile's items, applied to the UI first (the profile
 * query cache and the profile store, so that the app reflects them at once)
 * then sent to the API in order. A failed request reloads the profile.
 *
 * A drag & drop reorder no longer snaps back to the former order while the
 * request and the refetch are in flight.
 */
export function useProfileEditing(profileId: string) {
    const queryClient = useQueryClient();
    const upsert = useProfileStore(s => s.upsert);

    return useMemo(() => {
        const key = profileQueryKey(profileId);
        const read = () => queryClient.getQueryData<DisplayProfile>(key);
        const itemsOf = () => read()?.items ?? [];
        const setItems = (items: ProfileItem[]) => {
            const profile = read();
            if (profile) {
                const updated = {...profile, items};
                queryClient.setQueryData(key, updated);
                upsert(updated);
            }
        };

        /** Applies `optimistic`, queues `request`, reloads when it fails */
        const run = async <T>(
            optimistic: ProfileItem[] | undefined,
            request: () => Promise<T>
        ): Promise<T | undefined> => {
            if (optimistic) {
                setItems(optimistic);
            }
            try {
                return await enqueue(profileId, request);
            } catch (e: any) {
                toast.error(e?.message);
                void queryClient.invalidateQueries({queryKey: key});

                return undefined;
            }
        };

        /** Every item, `sectionIds` first in their order */
        const fullOrder = (sectionIds: string[]): ProfileItem[] => {
            const all = itemsOf();
            const byId = new Map(all.map(i => [i.id, i]));
            const moved = new Set(sectionIds);

            return [
                ...sectionIds
                    .map(id => byId.get(id))
                    .filter((i): i is ProfileItem => !!i),
                ...all.filter(i => !moved.has(i.id)),
            ];
        };
        const persistOrder = (ordered: ProfileItem[]) => {
            setItems(ordered);

            return sortProfileItems(
                profileId,
                ordered.map(i => i.id)
            );
        };

        return {
            /**
             * Adds items to the profile, appended; with `insert`, placed at
             * `index` among the items of `section` instead.
             */
            add: async (
                items: Omit<ProfileItem, 'id'>[],
                insert?: {section: ProfileItemSection; index: number}
            ): Promise<ProfileItem[]> => {
                const known = new Set(itemsOf().map(i => i.id));
                const added = await run(undefined, async () => {
                    const updated = await addToProfile(
                        profileId,
                        items.map(i => ({...i, id: ''}))
                    );
                    // Neither known when asked (an item removed since is
                    // still there) nor added since
                    const current = new Set(itemsOf().map(i => i.id));
                    const created = (updated.items ?? []).filter(
                        i => !known.has(i.id) && !current.has(i.id)
                    );
                    setItems([...itemsOf(), ...created]);
                    if (insert && created.length > 0) {
                        const createdIds = created.map(i => i.id);
                        const ids = itemsOf()
                            .filter(
                                i =>
                                    i.section === insert.section &&
                                    !createdIds.includes(i.id)
                            )
                            .map(i => i.id);
                        ids.splice(insert.index, 0, ...createdIds);
                        await persistOrder(fullOrder(ids));
                    }

                    return created;
                });

                return added ?? [];
            },

            remove: (ids: string[]) =>
                run(
                    itemsOf().filter(i => !ids.includes(i.id)),
                    () => removeFromProfile(profileId, ids)
                ),

            /** Reorders the items of a section */
            sort: (sectionIds: string[]) => {
                const ordered = fullOrder(sectionIds);

                return run(ordered, () =>
                    sortProfileItems(
                        profileId,
                        ordered.map(i => i.id)
                    )
                );
            },

            /** Patches items (the API takes the whole item) */
            update: (patches: {id: string; data: Partial<ProfileItem>}[]) => {
                const next = itemsOf().map(i => {
                    const patch = patches.find(p => p.id === i.id);

                    return patch ? {...i, ...patch.data} : i;
                });

                return run(next, () =>
                    Promise.all(
                        patches.map(({id}) =>
                            putProfileItem(
                                profileId,
                                id,
                                next.find(i => i.id === id)!
                            )
                        )
                    )
                );
            },
        };
    }, [profileId, queryClient, upsert]);
}
