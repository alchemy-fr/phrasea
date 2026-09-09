'use client';

import {useCallback, useEffect, useMemo, useState} from 'react';
import {useQuery} from '@tanstack/react-query';
import {AssetTypeFilter, type Attribute} from '@/types/api';
import {getAssetAttributes} from '@/lib/api/assets';
import {
    useDefinitionsStore,
    workspaceIdOf,
} from '@/features/attributes/definitionsStore';
import {
    AttributeIndex,
    buildAttributeIndex,
    computeBatchActions,
    DefinitionIndex,
} from './attributeEditorModel';
import type {OnAttributeChange} from './AttributesEditor';

/**
 * State of an attribute form for a workspace: loads definitions (and the
 * asset's current attributes when editing), tracks local edits and computes
 * the batch actions to persist.
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
    const loadWorkspace = useDefinitionsStore(s => s.loadWorkspace);
    const allDefinitions = useDefinitionsStore(s => s.definitions);

    useEffect(() => {
        if (workspaceId) {
            void loadWorkspace(workspaceId);
        }
    }, [workspaceId, loadWorkspace]);

    const definitions = useMemo<DefinitionIndex>(() => {
        const index: DefinitionIndex = {};
        allDefinitions
            .filter(
                d =>
                    workspaceId &&
                    workspaceIdOf(d) === workspaceId &&
                    (!target || !d.target || (d.target & target) !== 0)
            )
            .forEach(d => {
                index[d.id] = d;
            });

        return index;
    }, [allDefinitions, workspaceId, target]);

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

    useEffect(() => {
        setAttributes(remoteIndex);
        setDirty(false);
    }, [remoteIndex]);

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
        loading: remote.isLoading,
        applyRemote,
        setAttributes,
    };
}
