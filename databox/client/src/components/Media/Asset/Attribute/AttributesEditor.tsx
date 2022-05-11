import React, {useCallback, useState} from "react";
import {Button} from "@mui/material";
import {isSame} from "../../../../utils/comparison";
import {
    assetAttributeBatchUpdate,
    AttributeBatchAction,
    getAssetAttributes,
} from "../../../../api/asset";
import {Attribute, AttributeDefinition} from "../../../../types";
import AttributeType from "./AttributeType";
import {NO_LOCALE} from "../EditAssetAttributes";
import {toast} from "react-toastify";
import AppDialog from "../../../Layout/AppDialog";

export type AttrValue<T = string> = {
    id: T;
    value: any;
}

export type DefinitionIndex = Record<string, AttributeDefinition>;
export type LocalizedAttributeIndex<T = string> = { [locale: string]: AttrValue<T> | AttrValue<T>[] | undefined };
export type AttributeIndex<T = string> = { [definitionId: string]: LocalizedAttributeIndex<T> };

type Props = {
    assetId: string;
    definitions: DefinitionIndex;
    attributes: AttributeIndex;
    onClose: () => void;
}

let idInc = 1;

export function createNewValue(type: string): AttrValue<number> {
    switch (type) {
        default:
        case 'text':
            return {
                id: idInc++,
                value: '',
            };
    }
}

export function buildAttributeIndex(definitionIndex: DefinitionIndex, attributes: Attribute[]): AttributeIndex {
    const attributeIndex: AttributeIndex = {};
    Object.keys(definitionIndex).forEach((k) => {
        attributeIndex[definitionIndex[k].id] = {};
    });

    for (let a of attributes) {
        const l = a.locale || NO_LOCALE;
        const v = {
            id: a.id,
            value: a.value,
        };

        if (!attributeIndex[a.definition.id]) {
            attributeIndex[a.definition.id] = {};
        }

        if (definitionIndex[a.definition.id].multiple) {
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

export type OnChangeHandler = (defId: string, value: LocalizedAttributeIndex<string | number>) => void;

export default function AttributesEditor({
                                             assetId,
                                             definitions,
                                             attributes: initialAttrs,
                                             onClose,
                                         }: Props) {
    const [error, setError] = useState<string>();
    const [remoteAttrs, setRemoteAttrs] = useState<AttributeIndex>(initialAttrs);
    const [attributes, setAttributes] = useState<AttributeIndex<string | number>>(initialAttrs);
    const [saving, setSaving] = useState<any>(false);

    const onChange = useCallback((defId: string, value: LocalizedAttributeIndex<string | number> | undefined) => {
        setAttributes((prev: AttributeIndex<string | number>): AttributeIndex<string | number> => {
            const newValues = {...prev};

            if (value === undefined) {
                delete newValues[defId];
            } else {
                newValues[defId] = value;
            }

            return newValues;
        });
    }, [setAttributes]);

    const save = async () => {
        setSaving(true);
        try {
            const actions: AttributeBatchAction[] = [];

            Object.keys(attributes).forEach((defId): void => {
                const lv = attributes[defId];
                Object.keys(lv).forEach((locale): void => {
                    const currValue = lv[locale];
                    if (isSame(remoteAttrs[defId][locale], currValue)) {
                        return;
                    }

                    if (currValue) {
                        const removeV = remoteAttrs[defId][locale] as AttrValue[];
                        if (currValue instanceof Array) {
                            if (!removeV) {
                                actions.push({
                                    action: 'set',
                                    definitionId: defId,
                                    value: currValue.map(_v => _v.value),
                                    locale: locale !== NO_LOCALE ? locale : undefined,
                                });
                            } else {
                                currValue.forEach((v: AttrValue<string | number>) => {
                                    if (v.value) {
                                        const found = removeV.find(_v => _v.id === v.id);
                                        if (!found) {
                                            actions.push({
                                                action: 'add',
                                                definitionId: defId,
                                                value: v.value,
                                                locale: locale !== NO_LOCALE ? locale : undefined,
                                            });
                                        } else {
                                            if (!isSame(found.value, v.value)) {
                                                actions.push({
                                                    action: 'set',
                                                    id: found.id,
                                                    definitionId: defId,
                                                    value: v.value,
                                                    locale: locale !== NO_LOCALE ? locale : undefined,
                                                });
                                            }
                                        }
                                    } else if (typeof v.id === 'string') {
                                        actions.push({
                                            action: 'delete',
                                            definitionId: defId,
                                            id: v.id,
                                        });
                                    }
                                });
                            }
                        } else {
                            actions.push({
                                action: 'set',
                                definitionId: defId,
                                value: currValue.value,
                                locale: locale !== NO_LOCALE ? locale : undefined,
                            });
                        }
                    }
                });
            });

            Object.keys(remoteAttrs).forEach((defId): void => {
                Object.keys(remoteAttrs[defId]).forEach((locale): void => {
                    const remoteV = remoteAttrs[defId][locale];

                    if (remoteV) {
                        if (remoteV instanceof Array) {
                            const attrV = attributes[defId][locale] as AttrValue<string | number>[];

                            remoteV.forEach(v => {
                                const found = attrV.find(_v => _v.id === v.id);
                                if (!found) {
                                    actions.push({
                                        action: 'delete',
                                        definitionId: defId,
                                        id: v.id,
                                    });
                                }
                            });
                        } else {
                            if (!attributes[defId] || !attributes[defId][locale] || !(attributes[defId][locale] as AttrValue).value) {
                                actions.push({
                                    action: 'delete',
                                    definitionId: defId,
                                    id: remoteV.id,
                                });
                            }
                        }
                    }
                });
            });

            await assetAttributeBatchUpdate(assetId, actions);
            const res = await getAssetAttributes(assetId);
            const attributeIndex = buildAttributeIndex(definitions, res);

            setRemoteAttrs(attributeIndex);
            setAttributes(attributeIndex);

            toast.success("Attributes saved !", {});

            setSaving(false);

            // TODO
            if (error) {
                setError(undefined);
            }
        } catch (e: any) {
            console.error('e', e);
            setSaving(false);
            if (e.response && typeof e.response.data === 'object') {
                const data = e.response.data;
                setError(`${data['hydra:title']}: ${data['hydra:description']}`);
            } else {
                setError(e.toString());
            }
        }
    }

    return <AppDialog
        onClose={onClose}
        title={`Attributes`}
        actions={({onClose}) => <>
            {error && <div>{error}</div>}
            <Button
                variant="contained"
                disabled={saving || isSame(attributes, remoteAttrs)}
                onClick={save}
                color="primary">
                Save
            </Button>
            <Button
                onClick={onClose}
                className={'btn-secondary'}
            >
                Close
            </Button>
        </>}
    >
        {Object.keys(definitions).map(defId => {
            const d = definitions[defId];

            return <div
                key={defId}
            >
                <AttributeType
                    attributes={attributes[defId]}
                    disabled={saving}
                    definition={d}
                    onChange={onChange}
                />
                <hr/>
            </div>
        })}
    </AppDialog>
}
