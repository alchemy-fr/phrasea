import React, {useCallback, useEffect, useState} from 'react';
import {Box, Button, IconButton} from '@mui/material';
import {FormRow} from '@alchemy/react-form';
import {useTranslation} from 'react-i18next';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import AttributeWidget from "./AttributeWidget.tsx";
import {MultiValueIndex, MultiValueValue, Values} from "./types.ts";

type Props<T> = {
    id: string;
    type: string;
    name?: string;
    valueContainer: Values;
    onChange: (values: T[] | undefined) => void;
    isRtl: boolean;
    disabled: boolean;
    indeterminate?: boolean;
    readOnly?: boolean;
    locale: string;
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
    valueContainer,
    disabled,
    isRtl,
    onChange,
    type,
    readOnly,
    locale,
}: Props<T>) {
    const {t} = useTranslation();

    const toKey = (v: T): string => {
        if (!v) {
            return '';
        }

        return v as string;
    };

    const computed = React.useMemo<MultiValueValue<T>[]>(() => {
        const index: MultiValueIndex<T> = {};
        const length = valueContainer.values.length;

        valueContainer.values.forEach(translations => {
            const values = translations[locale];

            values?.forEach((v: T) => {
                const k = toKey(v);

                index[k] ??= {
                    p: 0,
                    v,
                };
                index[k].p++;
            })
        });

        return Object.keys(index).map((k: string) => {
            const v = index[k]!;

            return {
                key: k,
                part: Math.round(v.p / length * 10000) / 100,
                value: v.v,
            }
        })
            .sort((a, b) => a.part - b.part)
            .sort((a, b) => a.key.localeCompare(b.key))
            ;
    }, [valueContainer, locale]);

    const [values, setValues] = useState<MultiValueValue<T>[]>(
        computed ?? [{
            value: createNewValue(type),
            part: 100,
        }]
    );

    useEffect(() => {
        setValues(computed ?? []);
    }, [computed]);

    const changeHandler = useCallback(
        (index: number, value: T) => {
            setValues((prev) => {
                const nv = [...prev];

                nv[index] = {
                    value,
                    part: 100,
                    key: toKey(value),
                };

                setTimeout(() => onChange(nv.map(c => c.value)), 0);

                return nv;
            });
        },
        [setValues, onChange]
    );

    const add = () => {
        setValues(prev => {
            const nv = prev.concat(createNewValue(type));

            setTimeout(() => onChange(nv.map(c => c.value)), 0);

            return nv;
        });
    };

    const remove = (i: number) => {
        setValues(prev => {
            const nv = [...prev];
            nv.splice(i, 1);
            setTimeout(() => onChange(nv.map(c => c.value)), 0);

            return nv;
        });
    };

    const addToAll = (i: number) => {
        setValues(prev => {
            const nv = [...prev];

            nv[i].part = 100;

            setTimeout(() => onChange(nv.map(c => c.value)), 0);

            return nv;
        });
    };

    return (
        <FormRow>
            {values.map((v: MultiValueValue<T>, i: number) => {
                const indeterminate = v.part < 100;

                return (
                        <Box
                            key={i}
                            sx={{
                                display: 'flex',
                                opacity: indeterminate ? 0.7 : undefined,
                                p: 1,
                            }}
                        >
                            <div style={{
                                flexGrow: 1,
                            }}>
                                <AttributeWidget<T>
                                    readOnly={readOnly}
                                    value={v.value}
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
                            </div>
                            <div style={{
                            }}>
                                <IconButton
                                    disabled={!indeterminate || readOnly || disabled}
                                    onClick={() => addToAll(i)}
                                    color="success"
                                >
                                    <AddIcon/>
                                </IconButton>
                                <IconButton
                                    disabled={readOnly || disabled}
                                    onClick={() => remove(i)}
                                    color="error"
                                >
                                    <DeleteIcon/>
                                </IconButton>
                            </div>
                        </Box>
                );
            })}

            <Button
                sx={{mt: 2,}}
                startIcon={<AddIcon/>}
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
