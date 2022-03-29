import React, {useCallback, useState} from "react";
import {Button, InputLabel} from "@mui/material";
import AttributeWidget from "./AttributeWidget";
import {isSameArray} from "../../../../utils/array";
import {deleteAssetAttribute, putAssetAttribute} from "../../../../api/asset";

export type AttrValue<T = string> = {
    id: T;
    value: any;
}

type Props = {
    id: string;
    assetId: string;
    type: string;
    name: string;
    values: AttrValue[];
}

let idInc = 1;

function createNewValue(type: string): any {
    switch (type) {
        default:
        case 'text':
            return {
                id: idInc++,
                value: '',
            };
    }
}

export default function MultiAttributeRow({
                                         id,
                                         assetId,
                                         name,
                                         values: initialValues,
                                         type,
                                     }: Props) {
    const [error, setError] = useState<string>();

    const [realValue, setRealValue] = useState<AttrValue[]>(initialValues);
    const [values, setValues] = useState<AttrValue<string | number>[]>(initialValues.length > 0 ? initialValues : [createNewValue(type)]);
    const [saving, setSaving] = useState<any>(false);

    const onChange = useCallback((index: number, value: any) => {
        setValues((prev: AttrValue<string | number>[]): AttrValue<string | number>[] => {
            const newValues = [...prev];
            newValues[index] = {
                ...newValues[index],
                value,
            };

            return newValues;
        });
    }, [setValues]);

    const save = async () => {
        setSaving(true);
        try {
            const newValues: (AttrValue | undefined)[] = await Promise.all(values.map(async (v) => {
                let r: AttrValue | undefined;
                if ((r = realValue.find(iv => iv.id === v.id))) {
                    return r;
                }

                if (typeof v.id === 'string') {
                    if (!v.value) {
                        await deleteAssetAttribute(v.id);
                    } else {
                        await putAssetAttribute(
                            v.id,
                            assetId,
                            id,
                            v.value
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
                        id,
                        v.value
                    );

                    return {
                        id: result.id,
                        value: result.value,
                    }
                }
            }));

            await Promise.all(realValue.map(async (v) => {
                if (!values.some(cv => cv.id === v.id)) {
                    await deleteAssetAttribute(v.id as string);
                }
            }));

            // TODO update ID from API
            setRealValue((newValues.filter(v => !!v) as AttrValue[]));

            // TODO
            if (error) {
                setError(undefined);
            }
        } catch (e: any) {
            setSaving(false);
            if (e.response && typeof e.response.data === 'object') {
                const data = e.response.data;
                setError(`${data['hydra:title']}: ${data['hydra:description']}`);
            } else {
                setError(e.toString());
            }
        }
    }

    const add = () => {
        setValues(prev => prev.concat(createNewValue(type)));
    }

    const remove = (i: number) => {
        setValues(prev => {
            const nv = [...prev];
            nv.splice(i, 1);

            return nv;
        });
    }

    return <div
        className={'form-group'}
    >
        <InputLabel>{name}</InputLabel>

        {values.map((v: AttrValue<string | number>, i: number) => {
            return <div
                key={v.id}
            >
                <AttributeWidget
                    value={v.value}
                    disabled={saving}
                    type={type}
                    onChange={(v) => {
                        onChange(i, v);
                    }}
                    id={`${id}`}
                />
                <Button
                    variant="contained"
                    disabled={saving}
                    onClick={() => remove(i)}
                    color="secondary">
                    Remove
                </Button>
            </div>
        })}

        <Button
            variant="contained"
            disabled={saving}
            onClick={add}
            color="secondary">
            Add {name}
        </Button>

        <Button
            variant="contained"
            disabled={saving || isSameArray(values, realValue)}
            onClick={save}
            color="primary">
            Save
        </Button>
        {error && <div>{error}</div>}
    </div>
}
