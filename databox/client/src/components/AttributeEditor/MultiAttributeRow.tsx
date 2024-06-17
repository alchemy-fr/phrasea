import {useCallback, useEffect, useState} from 'react';
import {Button} from '@mui/material';
import {FormRow} from '@alchemy/react-form';
import {useTranslation} from 'react-i18next';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import AttributeWidget from "./AttributeWidget.tsx";

type Props<T> = {
    id: string;
    type: string;
    name?: string;
    values: T[] | undefined;
    onChange: (values: T[] | undefined) => void;
    isRtl: boolean;
    disabled: boolean;
    indeterminate?: boolean;
    readOnly?: boolean;
};

export function createNewValue(type: string): any {
    switch (type) {
        default:
        case 'text':
            return '';
    }
}

export default function MultiAttributeRow<T>({
    id,
    name,
    values: initialValues,
    disabled,
    isRtl,
    onChange,
    type,
    indeterminate,
    readOnly,
}: Props<T>) {
    const {t} = useTranslation();
    const [values, setValues] = useState<T[]>(
        initialValues && initialValues.length > 0 ? initialValues : [createNewValue(type) as T]
    );

    useEffect(() => {
        setValues(initialValues && initialValues.length > 0 ? initialValues as T[] : []);
    }, [initialValues]);

    const changeHandler = useCallback(
        (index: number, value: T) => {
            setValues(
                (
                    prev: T[]
                ): T[] => {
                    const nv = [...prev];

                    nv[index] = value;

                    setTimeout(() => onChange(nv), 0);

                    return nv;
                }
            );
        },
        [setValues, onChange]
    );

    const add = () => {
        setValues(prev => {
            const nv = prev.concat(createNewValue(type));

            setTimeout(() => onChange(nv), 0);

            return nv;
        });
    };

    const remove = (i: number) => {
        setValues(prev => {
            const nv = [...prev];
            nv.splice(i, 1);
            setTimeout(() => onChange(nv), 0);

            return nv;
        });
    };

    return (
        <FormRow>
            {values.map((v: T, i: number) => {
                return (
                    <div key={i}>
                        <FormRow
                            sx={{
                                display: 'flex',
                            }}
                        >
                            <AttributeWidget<T>
                                indeterminate={indeterminate}
                                readOnly={readOnly}
                                value={v}
                                isRtl={isRtl}
                                disabled={disabled}
                                name={`${name} #${i + 1}`}
                                type={type}
                                required={true}
                                onChange={v => {
                                    changeHandler(i, v!);
                                }}
                                id={`${id}_${i}`}
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
