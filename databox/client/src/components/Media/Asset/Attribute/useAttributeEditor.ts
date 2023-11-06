import React, {useCallback, useEffect, useState} from "react";
import {
    AttributeIndex,
    AttrValue,
    DefinitionIndex,
    NO_LOCALE,
    OnChangeHandler
} from "./AttributesEditor";
import {Attribute, AttributeDefinition} from "../../../../types";
import {getWorkspaceAttributeDefinitions} from "../../../../api/attributes";
import {getAssetAttributes} from "../../../../api/asset";
import {getBatchActions} from "./BatchActions";

export function useAttributeEditor({
    workspaceId,
    assetId,
}: {
    workspaceId: string | undefined;
    assetId?: string | string[] | undefined;
}) {
    const [state, setState] = useState<{
        remoteAttributes: AttributeIndex;
        definitionIndex: DefinitionIndex;
    }>();

    const [attributes, setAttributes] = useState<AttributeIndex<string | number> | undefined>();

    useEffect(() => {
        setAttributes(undefined);

        if (workspaceId) {
            (async () => {
                setAttributes(undefined);
                const promises: Promise<any>[] = [
                    getWorkspaceAttributeDefinitions(workspaceId!),
                ];
                if (assetId) {
                    promises.push(getAssetAttributes(assetId));
                }

                const [
                    definitions,
                    attributes,
                ]: [AttributeDefinition[], Attribute[]] = await Promise.all(promises) as any;

                const definitionIndex: DefinitionIndex = {};
                for (let ad of definitions) {
                    definitionIndex[ad.id] = ad;
                }

                const attributeIndex = buildAttributeIndex(definitionIndex, attributes ?? []);

                setAttributes(attributeIndex);
                setState({
                    definitionIndex,
                    remoteAttributes: attributeIndex,
                });
            })();
        }
    }, [workspaceId, assetId]);

    const onChangeHandler = useCallback<OnChangeHandler>((defId, locale, value) => {
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
    }, []);

    const setRemoteAttributes = React.useCallback((remoteAttributes: AttributeIndex) => {
        setState(p => ({
            ...(p!),
            remoteAttributes,
        }))
    }, []);

    const reset = React.useCallback(() => {
        setAttributes(state?.remoteAttributes);
    }, [state?.remoteAttributes]);

    return React.useMemo(() => {
        const reloadAssetAttributes = async (assetId: string) => {
            const res = await getAssetAttributes(assetId);
            const attributeIndex = buildAttributeIndex(state!.definitionIndex, res);

            setRemoteAttributes(attributeIndex);
            setAttributes(attributeIndex);
        };

        const getActions = () => {
            return getBatchActions(attributes!, state!.definitionIndex, state!.remoteAttributes);
        };

        return {
            definitionIndex: state?.definitionIndex,
            remoteAttributes: state?.remoteAttributes,
            reloadAssetAttributes,
            attributes,
            onChangeHandler,
            getActions,
            reset,
        }
    }, [state, workspaceId, attributes]);
}

export function buildAttributeIndex(definitionIndex: DefinitionIndex, attributes: Attribute[]): AttributeIndex {
    const attributeIndex: AttributeIndex = {};
    Object.keys(definitionIndex).forEach((k) => {
        attributeIndex[definitionIndex[k].id] = {};
    });

    for (let a of attributes) {
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
