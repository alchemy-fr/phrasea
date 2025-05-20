import {useCallback, useEffect, useState} from 'react';
import {Button} from '@mui/material';
import AttributeWidget from './AttributeWidget';
import {AttrValue, createNewValue} from './AttributesEditor';
import {FormRow} from '@alchemy/react-form';
import {useTranslation} from 'react-i18next';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import {AttributeWidgetOptions} from './types/types';
import {AttributeType} from "../../../../api/attributes.ts";

type Props = {
    id: string;
    type: AttributeType;
    name?: string;
    values: AttrValue<string | number>[];
    onChange: (values: AttrValue<string | number>[]) => void;
    isRtl: boolean;
    disabled: boolean;
    indeterminate?: boolean;
    readOnly?: boolean;
    options: AttributeWidgetOptions;
};

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
    options,
}: Props) {
    const {t} = useTranslation();
    const [values, setValues] = useState<AttrValue<string | number>[]>(
        initialValues.length > 0 ? initialValues : [createNewValue(type)]
    );

    useEffect(() => {
        setValues(initialValues.length > 0 ? initialValues : []);
    }, [initialValues]);

    const changeHandler = useCallback(
        (index: number, value: AttrValue<string | number>) => {
            setValues(
                (
                    prev: AttrValue<string | number>[]
                ): AttrValue<string | number>[] => {
                    const nv = [...prev];
                    nv[index] = {
                        ...nv[index],
                        value: value.value,
                    };

                    setTimeout(() => onChange(nv), deferred);

                    return nv;
                }
            );
        },
        [setValues, onChange]
    );

    const add = () => {
        setValues(prev => {
            const nv = prev.concat(createNewValue(type));

            setTimeout(() => onChange(nv), deferred);

            return nv;
        });
    };

    const remove = (i: number) => {
        setValues(prev => {
            const nv = [...prev];
            nv.splice(i, 1);
            setTimeout(() => onChange(nv), deferred);

            return nv;
        });
    };

    return (
        <FormRow>
            {values.map((v: AttrValue<string | number>, i: number) => {
                return (
                    <div key={v.id}>
                        <FormRow
                            sx={{
                                display: 'flex',
                            }}
                        >
                            <AttributeWidget
                                indeterminate={indeterminate}
                                readOnly={readOnly}
                                value={v}
                                isRtl={isRtl}
                                disabled={disabled}
                                name={`${name} #${i + 1}`}
                                type={type}
                                required={true}
                                onChange={v => {
                                    changeHandler(i, v);
                                }}
                                id={`${id}_${i}`}
                                options={options}
                            />
                            <Button
                                startIcon={<DeleteIcon />}
                                variant="outlined"
                                disabled={readOnly || disabled}
                                onClick={() => remove(i)}
                                color="error"
                            >
                                {t(
                                    'form.attribute.collection.item_remove',
                                    'Remove'
                                )}
                            </Button>
                        </FormRow>
                    </div>
                );
            })}

            <Button
                startIcon={<AddIcon />}
                variant="outlined"
                disabled={readOnly || disabled}
                onClick={add}
                color="secondary"
            >
                {t('form.attribute.collection.item_add', 'Add {{name}}', {
                    name,
                })}
            </Button>
        </FormRow>
    );
}
