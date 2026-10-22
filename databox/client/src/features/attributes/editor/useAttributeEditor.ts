'use client';

import {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import {useQuery} from '@tanstack/react-query';
import {AssetTypeFilter, type Attribute} from '@/types/api';
import {getAssetAttributes} from '@/lib/api/assets';
import {useAttributeDefinitionList} from '@/features/attributes/useAttributeDefinitionList';
import {
    AttributeIndex,
    buildAttributeIndex,
    computeBatchActions,
    DefinitionIndex,
} from './attributeEditorModel';
import type {OnAttributeChange} from './AttributesEditor';

/**
 * State of an attribute form for a workspace: loads definitions page by page
 * (`hasMore` / `loadMore`) and the asset's current attributes when editing,
 * tracks local edits and computes the batch actions to persist.
 */
export function useAttributeEditor({
    workspaceId,
    assetId,
    target,
}: {
    workspaceId: string | undefined;
    assetId?: string;
    target: AssetTypeFilter;
}) {
    const filters = useMemo(
        () => ({workspaceId, target}),
        [workspaceId, target]
    );
    const list = useAttributeDefinitionList(filters, {
        pageSize: 100,
        enabled: !!workspaceId,
    });

    const definitions = useMemo<DefinitionIndex>(() => {
        const index: DefinitionIndex = {};
        if (workspaceId) {
            list.items.forEach(d => {
                index[d.id] = d;
            });
        }

        return index;
    }, [list.items, workspaceId]);

    const remote = useQuery({
        queryKey: ['asset-attributes', assetId],
        queryFn: () => getAssetAttributes(assetId!),
        enabled: !!assetId,
        staleTime: 0,
    });

    const remoteIndex = useMemo(
        () => buildAttributeIndex(definitions, remote.data ?? []),
        [definitions, remote.data]
    );
    const [attributes, setAttributes] = useState<AttributeIndex>(remoteIndex);
    const [dirty, setDirty] = useState(false);

    // Reset on other values (or another asset, workspace); the definitions of
    // a new page only add their fields, the edits are kept
    const source = useRef<{
        data: unknown;
        assetId?: string;
        workspaceId?: string;
    }>(undefined);
    useEffect(() => {
        const last = source.current;
        if (
            !last ||
            last.data !== remote.data ||
            last.assetId !== assetId ||
            last.workspaceId !== workspaceId
        ) {
            source.current = {data: remote.data, assetId, workspaceId};
            setAttributes(remoteIndex);
            setDirty(false);

            return;
        }
        setAttributes(prev => {
            const added = Object.keys(remoteIndex).filter(id => !(id in prev));

            return added.length === 0
                ? prev
                : {
                      ...prev,
                      ...Object.fromEntries(
                          added.map(id => [id, remoteIndex[id]])
                      ),
                  };
        });
    }, [remoteIndex, remote.data, assetId, workspaceId]);

    const onChange = useCallback<OnAttributeChange>((defId, locale, value) => {
        setDirty(true);
        setAttributes(prev => {
            const next = {...prev, [defId]: {...(prev[defId] ?? {})}};
            if (value === undefined) {
                delete next[defId][locale];
            } else {
                next[defId][locale] = value;
            }

            return next;
        });
    }, []);

    const getActions = useCallback(
        () => computeBatchActions(attributes, definitions, remoteIndex),
        [attributes, definitions, remoteIndex]
    );

    const reset = useCallback(() => {
        setAttributes(remoteIndex);
        setDirty(false);
    }, [remoteIndex]);

    const applyRemote = useCallback(
        (attrs: Attribute[]) => {
            const index = buildAttributeIndex(definitions, attrs);
            setAttributes(index);
            setDirty(false);
        },
        [definitions]
    );

    return {
        definitions,
        attributes,
        onChange,
        getActions,
        dirty,
        reset,
        loading: remote.isLoading || list.loading,
        hasMore: list.hasMore,
        loadMore: list.loadMore,
        loadingMore: list.loadingMore,
        applyRemote,
        setAttributes,
    };
}
