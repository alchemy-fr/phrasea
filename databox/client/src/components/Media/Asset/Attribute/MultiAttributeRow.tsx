import React, {useCallback, useEffect, useState} from "react";
import {Button} from "@mui/material";
import AttributeWidget from "./AttributeWidget";
import {AttrValue, createNewValue} from "./AttributesEditor";
import FormRow from "../../../Form/FormRow";
import {useTranslation} from 'react-i18next';

type Props = {
    id: string;
    type: string;
    name?: string;
    values: AttrValue<string | number>[];
    onChange: (values: AttrValue<string | number>[]) => void;
    isRtl: boolean;
    disabled: boolean;
    indeterminate?: boolean;
    readOnly?: boolean;
}

const deferred = 0;

export default function MultiAttributeRow({
    id,
    name,
    values: initialValues,
    disabled,
    isRtl,
    onChange,
    type,
    indeterminate,
    readOnly,
}: Props) {
    const {t} = useTranslation();
    const [values, setValues] = useState<AttrValue<string | number>[]>(initialValues.length > 0 ? initialValues : [createNewValue(type)]);

    useEffect(() => {
        setValues(initialValues.length > 0 ? initialValues : []);
    }, [initialValues]);

    const changeHandler = useCallback((index: number, value: AttrValue<string | number>) => {
        setValues((prev: AttrValue<string | number>[]): AttrValue<string | number>[] => {
            const nv = [...prev];
            nv[index] = {
                ...nv[index],
                value: value.value,
            };

            setTimeout(() => onChange(nv), deferred);

            return nv;
        });
    }, [setValues, onChange]);

    const add = () => {
        setValues(prev => {
            const nv = prev.concat(createNewValue(type));

            setTimeout(() => onChange(nv), deferred);

            return nv;
        });
    }

    const remove = (i: number) => {
        setValues(prev => {
            const nv = [...prev];
            nv.splice(i, 1);
            setTimeout(() => onChange(nv), deferred);

            return nv;
        });
    }

    return <FormRow>
        {values.map((v: AttrValue<string | number>, i: number) => {
            return <div
                key={v.id}
            >
                <FormRow
                    sx={{
                        display: 'flex',
                    }}>
                    <AttributeWidget
                        indeterminate={indeterminate}
                        readOnly={readOnly}
                        value={v}
                        isRtl={isRtl}
                        disabled={disabled}
                        name={`${name} #${i + 1}`}
                        type={type}
                        required={true}
                        onChange={(v) => {
                            changeHandler(i, v);
                        }}
                        id={`${id}_${i}`}
                    />
                    <Button
                        variant="contained"
                        disabled={readOnly || disabled}
                        onClick={() => remove(i)}
                        color="secondary">
                        {t('form.attribute.collection.item_remove', 'Remove')}
                    </Button>
                </FormRow>
            </div>
        })}

        <Button
            variant="contained"
            disabled={readOnly || disabled}
            onClick={add}
            color="secondary">
            Add {name}
        </Button>
    </FormRow>
}
