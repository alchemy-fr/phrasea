'use client';

import {useCallback} from 'react';
import {useQuery} from '@tanstack/react-query';
import {
    getDataboxEntityValues,
    getDataboxFields,
    getDataboxTags,
    getDataboxWorkspaces,
    getTargetDestination,
} from '@/lib/api/uploader';
import type {FieldOptionsLoader} from '@/features/form-builder/types';

export function useDataboxWorkspaces() {
    return useQuery({
        queryKey: ['databox', 'workspaces'],
        queryFn: getDataboxWorkspaces,
        staleTime: 60_000,
        retry: false,
    });
}

export function useDataboxFields(workspaceId: string | null | undefined) {
    return useQuery({
        queryKey: ['databox', 'workspaces', workspaceId, 'fields'],
        queryFn: () => getDataboxFields(workspaceId!),
        enabled: !!workspaceId,
        staleTime: 60_000,
        retry: false,
    });
}

/** The workspace of the Databox collection the target uploads to, if any */
export function useTargetWorkspace(targetId: string | undefined) {
    return useQuery({
        queryKey: ['databox', 'targets', targetId, 'destination'],
        queryFn: async () =>
            (await getTargetDestination(targetId!))?.workspaceId ?? null,
        enabled: !!targetId,
        staleTime: 60_000,
        retry: false,
    });
}

/** The values of the entity and tag fields, read from Databox directly */
export function useDataboxOptionsLoader(
    workspaceId: string | null | undefined
): FieldOptionsLoader {
    return useCallback(
        async (field, query) => {
            if (field.type === 'tag' && workspaceId) {
                return getDataboxTags(workspaceId, query);
            }
            if (field.type === 'entity' && field.attribute?.entityListId) {
                return getDataboxEntityValues(
                    field.attribute.entityListId,
                    query
                );
            }

            return {member: [], hasMore: false};
        },
        [workspaceId]
    );
}
