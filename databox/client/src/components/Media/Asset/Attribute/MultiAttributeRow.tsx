import React, {useCallback, useState} from "react";
import {Button, InputLabel} from "@mui/material";
import AttributeWidget from "./AttributeWidget";
import {AttrValue, createNewValue} from "./AttributesEditor";

type Props = {
    id: string;
    type: string;
    name: string;
    values: AttrValue<string | number>[];
    onChange: (values: AttrValue<string | number>[]) => void;
    disabled: boolean;
}

export default function MultiAttributeRow({
                                         id,
                                         name,
                                         values: initialValues,
    disabled,
                                              onChange,
                                         type,
                                     }: Props) {
    const [values, setValues] = useState<AttrValue<string | number>[]>(initialValues.length > 0 ? initialValues : [createNewValue(type)]);

    const changeHandler = useCallback((index: number, value: any) => {
        setValues((prev: AttrValue<string | number>[]): AttrValue<string | number>[] => {
            const newValues = [...prev];
            newValues[index] = {
                ...newValues[index],
                value,
            };

            onChange(newValues);

            return newValues;
        });
    }, [setValues]);

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
                    disabled={disabled}
                    name={`${name} #${i + 1}`}
                    type={type}
                    required={true}
                    onChange={(v) => {
                        changeHandler(i, v);
                    }}
                    id={`${id}`}
                />
                <Button
                    variant="contained"
                    disabled={disabled}
                    onClick={() => remove(i)}
                    color="secondary">
                    Remove
                </Button>
            </div>
        })}

        <hr/>
        <Button
            variant="contained"
            disabled={disabled}
            onClick={add}
            color="secondary">
            Add {name}
        </Button>
    </div>
}
