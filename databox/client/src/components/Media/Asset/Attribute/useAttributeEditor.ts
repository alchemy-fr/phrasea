import React, {useCallback, useEffect, useState} from "react";
import {AttributeIndex, buildAttributeIndex, DefinitionIndex, OnChangeHandler} from "./AttributesEditor";
import {Attribute, AttributeDefinition} from "../../../../types";
import {getWorkspaceAttributeDefinitions} from "../../../../api/attributes";
import {getAssetAttributes} from "../../../../api/asset";
import {getBatchActions} from "./BatchActions";

export function useAttributeEditor(
    workspaceId: string,
    assetId?: string | string[] | undefined,
) {
    const [state, setState] = useState<{
        remoteAttributes: AttributeIndex;
        definitionIndex: DefinitionIndex;
    }>();

    const [attributes, setAttributes] = useState<AttributeIndex<string | number>>();

    useEffect(() => {
        (async () => {
            const promises: Promise<any>[] = [
                getWorkspaceAttributeDefinitions(workspaceId),
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

            const attributeIndex = buildAttributeIndex(definitionIndex, attributes);
            setState({
                definitionIndex,
                remoteAttributes: attributeIndex,
            });
            setAttributes(attributeIndex);
        })();
    }, [assetId]);

    const onChange = useCallback<OnChangeHandler>((defId, locale, value) => {
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

    const getActions = React.useCallback(() => {
        return getBatchActions(attributes!, state!.definitionIndex, state!.remoteAttributes);
    }, [attributes, state]);

    const setRemoteAttributes = React.useCallback((remoteAttributes: AttributeIndex) => {
        setState(p => ({
            ...(p!),
            remoteAttributes,
        }))
    }, []);

    return {
        definitionIndex: state?.definitionIndex,
        remoteAttributes: state?.remoteAttributes,
        setRemoteAttributes,
        attributes,
        onChange,
        getActions,
    }
}
