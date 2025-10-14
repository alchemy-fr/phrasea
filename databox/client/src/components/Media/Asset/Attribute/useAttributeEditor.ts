import React, {useCallback, useEffect} from 'react';
import {
    AttributeIndex,
    AttrValue,
    DefinitionIndex,
    OnChangeHandler,
} from './AttributesEditor';
import {AssetTypeFilter, Attribute} from '../../../../types';
import {getAsset, getAssetAttributes} from '../../../../api/asset';
import {getBatchActions} from './BatchActions';
import {NO_LOCALE} from './constants.ts';
import {useAssetStore} from '../../../../store/assetStore.ts';
import {
    useAttributeDefinitionStore,
    useIndexById,
} from '../../../../store/attributeDefinitionStore.ts';

type Props = {
    workspaceId: string | undefined;
    assetId?: string | string[] | undefined;
    target: AssetTypeFilter;
};

export function useAttributeEditor({workspaceId, assetId, target}: Props) {
    const [dirty, setDirty] = React.useState(false);
    const [remoteAttributes, setRemoteAttributes] =
        React.useState<Attribute[]>();

    const updateAsset = useAssetStore(s => s.update);
    const loadWorkspaceDefinitions = useAttributeDefinitionStore(
        s => s.loadWorkspace
    );
    const definitionIndex = useIndexById({workspaceId, target});
    const [attributes, setAttributes] = React.useState<
        AttributeIndex<string | number>
    >(buildAttributeIndex(definitionIndex, remoteAttributes ?? []));

    useEffect(() => {
        setAttributes(
            buildAttributeIndex(definitionIndex, remoteAttributes ?? [])
        );
    }, [definitionIndex, remoteAttributes]);

    useEffect(() => {
        setRemoteAttributes(undefined);
        if (workspaceId) {
            loadWorkspaceDefinitions(workspaceId);
            if (assetId) {
                getAssetAttributes(assetId).then(setRemoteAttributes);
            }
        }
    }, [loadWorkspaceDefinitions, workspaceId, assetId]);

    const onChangeHandler = useCallback<OnChangeHandler>(
        (defId, locale, value) => {
            setDirty(true);
            setAttributes((prev): AttributeIndex<string | number> => {
                const newValues = {...prev!};

                if (value === undefined) {
                    if (newValues[defId]) {
                        delete newValues[defId][locale];
                    }
                } else {
                    newValues[defId] = {
                        ...(newValues[defId] ?? {}),
                        [locale]: value,
                    };
                }

                if (Object.keys(newValues[defId]).length === 0) {
                    delete newValues[defId];
                }

                return newValues;
            });
        },
        []
    );

    const reset = React.useCallback(() => {
        setAttributes(
            buildAttributeIndex(definitionIndex, remoteAttributes ?? [])
        );
    }, [remoteAttributes]);

    return React.useMemo(() => {
        const reloadAssetAttributes = async (assetId: string) => {
            const res = await getAsset(assetId);

            updateAsset(res);

            const attributeIndex = buildAttributeIndex(
                definitionIndex,
                res.attributes
            );

            setDirty(false);
            setRemoteAttributes(res.attributes);
            setAttributes(attributeIndex);
        };

        const getActions = () =>
            getBatchActions(
                attributes!,
                definitionIndex,
                buildAttributeIndex(definitionIndex, remoteAttributes ?? [])
            );

        return {
            definitionIndex,
            remoteAttributes,
            reloadAssetAttributes,
            attributes,
            onChangeHandler,
            getActions,
            reset,
            dirty,
        };
    }, [
        definitionIndex,
        remoteAttributes,
        workspaceId,
        attributes,
        updateAsset,
    ]);
}

export function buildAttributeIndex(
    definitionIndex: DefinitionIndex,
    attributes: Attribute[]
): AttributeIndex {
    const attributeIndex: AttributeIndex = {};
    Object.keys(definitionIndex).forEach(k => {
        attributeIndex[definitionIndex[k].id] = {};
    });

    for (const a of attributes) {
        const def = definitionIndex[a.definition.id];
        if (!def) {
            continue;
        }

        const l = a.locale || NO_LOCALE;
        const v = {
            id: a.id,
            value: a.value,
        };

        if (!attributeIndex[a.definition.id]) {
            attributeIndex[a.definition.id] = {};
        }

        if (def.multiple) {
            if (!attributeIndex[a.definition.id][l]) {
                attributeIndex[a.definition.id][l] = [];
            }
            (attributeIndex[a.definition.id][l]! as AttrValue[]).push(v);
        } else {
            attributeIndex[a.definition.id][l] = v;
        }
    }

    return attributeIndex;
}
