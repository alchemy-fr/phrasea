import React, {useCallback, useState} from "react";
import {Box, Button} from "@mui/material";
import {isSame} from "../../../../utils/comparison";
import {attributeBatchUpdate, AttributeBatchAction, getAssetAttributes,} from "../../../../api/asset";
import {Asset, Attribute, AttributeDefinition} from "../../../../types";
import AttributeType from "./AttributeType";
import {NO_LOCALE} from "../EditAssetAttributes";
import {toast} from "react-toastify";
import AppDialog from "../../../Layout/AppDialog";
import SaveIcon from "@mui/icons-material/Save";
import {useTranslation} from 'react-i18next';
import {LoadingButton} from "@mui/lab";

export type AttrValue<T = string> = {
    id: T;
    value: any;
}

export type DefinitionIndex = Record<string, AttributeDefinition>;
export type LocalizedAttributeIndex<T = string> = { [locale: string]: AttrValue<T> | AttrValue<T>[] | undefined };
export type AttributeIndex<T = string> = { [definitionId: string]: LocalizedAttributeIndex<T> };

type Props = {
    assetId: string | string[];
    multiAssets?: Asset[];
    definitions: DefinitionIndex;
    attributes: AttributeIndex;
    onClose: () => void;
    onEdit: () => void;
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

export type OnChangeHandler = (defId: string, value: LocalizedAttributeIndex<string | number>) => void;

export default function AttributesEditor({
                                             assetId,
                                             definitions,
                                             attributes: initialAttrs,
                                             onClose,
                                             onEdit,
                                         }: Props) {
    const {t} = useTranslation();
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
                if (!definitions[defId].canEdit) {
                    return;
                }

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

            await attributeBatchUpdate(assetId, actions);
            const res = await getAssetAttributes(assetId);
            const attributeIndex = buildAttributeIndex(definitions, res);

            setRemoteAttrs(attributeIndex);
            setAttributes(attributeIndex);

            toast.success("Attributes saved !", {});

            setSaving(false);

            if (error) {
                setError(undefined);
            }

            onEdit();
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
        loading={saving}
        onClose={onClose}
        title={t(`form.attributes.title`, `Edit asset attributes`)}
        actions={({onClose}) => <>
            <Button
                onClick={onClose}
                color={'warning'}
                disabled={saving}
            >
                {t('dialog.cancel', 'Cancel')}
            </Button>
            <LoadingButton
                startIcon={<SaveIcon/>}
                type={'submit'}
                onClick={save}
                color={'primary'}
                disabled={saving}
                loading={saving}
            >
                {t('dialog.save', 'Save')}
            </LoadingButton>
        </>}
    >
        {Object.keys(definitions).map(defId => {
            const d = definitions[defId];

            return <Box
                key={defId}
                sx={{
                    mb: 5
                }}
            >
                <AttributeType
                    readOnly={!d.canEdit}
                    attributes={attributes[defId]}
                    disabled={saving}
                    definition={d}
                    onChange={onChange}
                />
            </Box>
        })}
    </AppDialog>
}
