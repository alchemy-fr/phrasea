import {useCallback, useEffect, useRef, useState} from 'react';
import {Box, Button} from '@mui/material';
import AttributeWidget from './AttributeWidget';
import {AttrValue} from './AttributesEditor';
import {FormRow} from '@alchemy/react-form';
import {useTranslation} from 'react-i18next';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import {MultiAttributeRowProps} from './attributeTypes.ts';
import {createNewValue} from './values.ts';

export default function MultiAttributeRow({
    id,
    label,
    values: initialValues,
    disabled,
    isRtl,
    onChange,
    type,
    readOnly,
    options,
    ...attributeProps
}: MultiAttributeRowProps) {
    const deferred = 0;
    const interactedRef = useRef(false);
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
        interactedRef.current = true;
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
        <FormRow
            sx={{
                mt: 1,
                display: 'flex',
                flexDirection: 'column',
                gap: 1,
            }}
        >
            {values.map((v: AttrValue<string | number>, i: number) => {
                return (
                    <div key={v.id}>
                        <Box
                            sx={{
                                display: 'flex',
                                gap: 1,
                            }}
                        >
                            <AttributeWidget
                                readOnly={readOnly}
                                value={v}
                                autoFocus={
                                    interactedRef.current &&
                                    i === values.length - 1
                                }
                                isRtl={isRtl}
                                disabled={disabled}
                                label={`${label} #${i + 1}`}
                                type={type}
                                required={true}
                                onChange={v => {
                                    changeHandler(i, v);
                                }}
                                id={`${id}_${i}`}
                                options={options}
                                {...attributeProps}
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
                        </Box>
                    </div>
                );
            })}

            <div>
                <Button
                    startIcon={<AddIcon />}
                    variant="outlined"
                    disabled={readOnly || disabled}
                    onClick={add}
                    color="secondary"
                >
                    {t('form.attribute.collection.item_add', 'Add {{name}}', {
                        name: label,
                    })}
                </Button>
            </div>
        </FormRow>
    );
}
