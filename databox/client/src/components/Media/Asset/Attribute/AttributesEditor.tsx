import React, {useCallback, useState} from "react";
import {Button} from "@mui/material";
import {isSame} from "../../../../utils/comparison";
import {deleteAssetAttribute, putAssetAttribute} from "../../../../api/asset";
import {AttributeDefinition} from "../../../../types";
import AttributeType from "./AttributeType";
import Modal from "../../../Layout/Modal";
import {NO_LOCALE} from "../EditAssetAttributes";
import {toast} from "react-toastify";

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
            const newValues: AttributeIndex = {};

            await Promise.all(Object.keys(attributes).map(async (defId): Promise<void> => {
                const lv = attributes[defId];
                if (remoteAttrs[defId] && isSame(remoteAttrs[defId], lv)) {
                    newValues[defId] = lv as LocalizedAttributeIndex;
                    return;
                }

                await Promise.all(Object.keys(attributes[defId]).map(async (locale) => {
                    const currValue = lv[locale];

                    const updateAttr = async (
                        removeValue: AttrValue | undefined,
                        v: AttrValue<string | number>,
                        position?: number
                    ): Promise<AttrValue | undefined> => {
                        if (isSame(removeValue, v)) {
                            return removeValue;
                        }

                        if (!v) {
                            return undefined;
                        }

                        if (typeof v.id === 'string') {
                            if (!v.value) {
                                await deleteAssetAttribute(v.id as string);
                            } else {
                                await putAssetAttribute(
                                    v.id,
                                    assetId,
                                    defId,
                                    v.value,
                                    locale !== NO_LOCALE ? locale : undefined
                                );

                                return {
                                    id: v.id,
                                    value: v.value,
                                };
                            }
                        } else {
                            const result = await putAssetAttribute(
                                undefined,
                                assetId,
                                defId,
                                v.value,
                                locale !== NO_LOCALE ? locale : undefined,
                                position
                            );

                            return {
                                id: result.id,
                                value: result.value,
                            }
                        }
                    };

                    if (!newValues[defId]) {
                        newValues[defId] = {};
                    }
                    if (currValue instanceof Array) {
                        newValues[defId][locale] = await Promise.all(
                            currValue.map((_v, pos) => {
                                const rv = remoteAttrs[defId][locale] ? (remoteAttrs[defId][locale] as AttrValue[]).find(
                                    __v => __v.id === _v.id
                                ) : undefined;

                                return updateAttr(rv, _v, pos) as Promise<AttrValue>
                            })
                        );
                    } else {
                        newValues[defId][locale] = await updateAttr(remoteAttrs[defId][locale] as AttrValue | undefined, currValue as AttrValue<string | number>);
                    }
                }));
            }));

            await Promise.all(Object.keys(remoteAttrs).map(async (defId): Promise<void> => {
                await Promise.all(Object.keys(remoteAttrs[defId]).map(async (locale) => {
                    const v = remoteAttrs[defId][locale];

                    if (v instanceof Array) {
                        await Promise.all(v.map(async (value: AttrValue<string | number>) => {
                            if (!newValues[defId]
                                || !newValues[defId][locale]
                                || !(newValues[defId][locale] instanceof Array)
                                || !(newValues[defId][locale] as AttrValue<string | number>[]).some(v => v.id === value.id)
                            ) {
                                await deleteAssetAttribute(value.id as string);
                            }
                        }));
                    } else {
                        if (!newValues[defId] || !newValues[defId][locale] || !(newValues[defId][locale] as AttrValue).value) {
                            await deleteAssetAttribute((v as AttrValue).id);
                        }
                    }
                }));
            }));

            setRemoteAttrs(newValues);
            setAttributes(newValues);

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

    return <Modal
        onClose={onClose}
        header={() => <div>Attributes</div>}
        footer={({onClose}) => <>
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
    </Modal>
}
