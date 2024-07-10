import React, {useContext, useEffect, useState} from 'react';
import {Box, Button, IconButton} from '@mui/material';
import {FormRow} from '@alchemy/react-form';
import {useTranslation} from 'react-i18next';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import {MultiValueIndex, MultiValueValue, SetAttributeValue, ToKeyFunc, Values,} from './types.ts';
import {getAttributeType} from '../Media/Asset/Attribute/types';
import {AttributeFormatterProps} from '../Media/Asset/Attribute/types/types';
import {AttributeFormatContext} from '../Media/Asset/Attribute/Format/AttributeFormatContext.ts';
import AttributeWidget from './AttributeWidget.tsx';

type Props<T> = {
    id: string;
    type: string;
    name: string;
    valueContainer: Values;
    disabled: boolean;
    indeterminate?: boolean;
    readOnly?: boolean;
    locale: string;
    toKey: ToKeyFunc<T>;
    setAttributeValue: SetAttributeValue<T>;
};

export default function MultiAttributeRow<T>({
    id,
    name,
    valueContainer,
    disabled,
    type,
    readOnly,
    locale,
    toKey,
    setAttributeValue,
}: Props<T>) {
    const inputRef = React.useRef<HTMLInputElement | null>(null);
    const {t} = useTranslation();
    const formatContext = useContext(AttributeFormatContext);
    const [newValue, setNewValue] = React.useState<T | undefined>();
    const definitionRef = React.useRef<string>(id);
    const [selected, setSelected] = React.useState<string | undefined>();

    const addValueHandler = React.useCallback(
        (v: T) => {
            setAttributeValue(v, {
                add: true,
                updateInput: true,
            });
            setNewValue(undefined);
        },
        [setAttributeValue]
    );

    const addHandler = React.useCallback(() => {
        addValueHandler(newValue!);
    }, [addValueHandler, newValue]);

    const removeValueHandler = React.useCallback(
        (v: T) => {
            setAttributeValue(v, {
                remove: true,
                updateInput: true,
            });
        },
        [setAttributeValue]
    );

    const changeNewItemHandler = React.useCallback(
        (v: T | undefined) => {
            setNewValue(v);
        },
        [setNewValue]
    );

    const computed = React.useMemo<MultiValueValue<T>[]>(() => {
        const index: MultiValueIndex<T> = {};
        const length = valueContainer.values.length;

        valueContainer.values.forEach(translations => {
            const values = translations[locale];

            values?.forEach((v: T) => {
                const k = toKey(type, v);

                index[k] ??= {
                    p: 0,
                    v,
                };
                index[k].p++;
            });
        });

        return Object.keys(index)
            .map((k: string) => {
                const v = index[k]!;

                return {
                    key: k,
                    part: Math.round((v.p / length) * 10000) / 100,
                    value: v.v,
                };
            })
            .sort((a, b) => a.part - b.part)
            .sort((a, b) => a.key.localeCompare(b.key));
    }, [valueContainer, locale]);

    const [values, setValues] = useState<MultiValueValue<T>[]>(computed ?? []);

    const finalValues = definitionRef.current !== id ? (computed ?? []) : values;
    const finalNewValue = definitionRef.current !== id ? undefined : newValue;

    useEffect(() => {
        setValues(computed ?? []);
        setNewValue(undefined);
        definitionRef.current = id;
    }, [computed, id]);


    const formatter = getAttributeType(type);

    return (
        <FormRow>
            <AttributeWidget<T>
                id={id}
                key={id}
                name={name}
                inputRef={inputRef}
                type={type}
                isRtl={false}
                disabled={disabled}
                required={false}
                autoFocus={true}
                value={finalNewValue}
                onChange={changeNewItemHandler}
            />
            <Button
                sx={{mb: 2}}
                startIcon={<AddIcon/>}
                variant="outlined"
                disabled={readOnly || disabled}
                color="primary"
                onClick={addHandler}
            >
                {t(
                    'form.attribute.collection.item_add',
                    'Add {{name}}',
                    {
                        name,
                    }
                )}
            </Button>

            {finalValues.map((v: MultiValueValue<T>, i: number) => {
                const valueFormatterProps: AttributeFormatterProps = {
                    value: v.value,
                    locale,
                    format: formatContext.formats[type],
                };

                const indeterminate = v.part < 100;
                const isSelected = selected === v.key;

                return (
                    <Box
                        key={i}
                        onClick={() => setSelected(v.key)}
                        sx={{
                            display: 'flex',
                            p: 1,
                            bgcolor: isSelected ? 'divider' : undefined,
                            '.vw': {
                                opacity: indeterminate ? 0.5 : undefined,
                            }
                        }}
                    >
                        <div
                            style={{
                                flexGrow: 1,
                            }}
                            className={'vw'}
                        >
                            {formatter.formatValue(valueFormatterProps)}
                        </div>
                        <div style={{}}>
                            <IconButton
                                disabled={
                                    !indeterminate || readOnly || disabled
                                }
                                onClick={() => addValueHandler(v.value)}
                                color="success"
                            >
                                <AddIcon/>
                            </IconButton>
                            <IconButton
                                disabled={readOnly || disabled}
                                onClick={() => removeValueHandler(v.value)}
                                color="error"
                            >
                                <DeleteIcon/>
                            </IconButton>
                        </div>
                    </Box>
                );
            })}
        </FormRow>
    );
}
