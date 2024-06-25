import React, {useContext, useEffect, useState} from 'react';
import {Box, Button, IconButton} from '@mui/material';
import {FormRow} from '@alchemy/react-form';
import {useTranslation} from 'react-i18next';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import {MultiValueIndex, MultiValueValue, SetAttributeValue, ToKeyFunc, Values} from "./types.ts";
import {getAttributeType} from "../Media/Asset/Attribute/types";
import {AttributeFormatterProps} from "../Media/Asset/Attribute/types/types";
import {AttributeFormatContext} from "../Media/Asset/Attribute/Format/AttributeFormatContext.ts";
import AttributeWidget from "./AttributeWidget.tsx";

export function createNewValue(type: string): any {
    switch (type) {
        default:
        case 'text':
            return '';
    }
}

type Props<T> = {
    id: string;
    type: string;
    name: string;
    valueContainer: Values;
    disabled: boolean;
    indeterminate?: boolean;
    readOnly?: boolean;
    locale: string;
    toKey: ToKeyFunc<T>,
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
    const [addNew, setAddNew] = React.useState(false);
    const [newValue, setNewValue] = React.useState<T | undefined>();

    const newHandler = React.useCallback(() => {
        setAddNew(true);
    }, []);

    const focus = () => inputRef.current?.focus();

    const addValueHandler = React.useCallback((v: T) => {
        setAttributeValue(v, {
            add: true,
            updateInput: true,
        });
        setNewValue(undefined);
        focus();
    }, [setAttributeValue]);

    const addHandler = React.useCallback(() => {
        addValueHandler(newValue!);
        focus();
    }, [addValueHandler, newValue]);

    const removeValueHandler = React.useCallback((v: T) => {
        setAttributeValue(v, {
            remove: true,
            updateInput: true,
        });
        focus();
    }, [setAttributeValue]);

    const ChangeNewItemHandler = React.useCallback((v: T | undefined) => {
        setNewValue(v);
    }, [setNewValue]);

    const computed = React.useMemo<MultiValueValue<T>[]>(() => {
        const index: MultiValueIndex<T> = {};
        const length = valueContainer.values.length;

        valueContainer.values.forEach(translations => {
            const values = translations[locale];

            console.log('values', values);

            values?.forEach((v: T) => {
                const k = toKey(type, v);

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

    const formatter = getAttributeType(type);

    return (
        <FormRow>
            {values.map((v: MultiValueValue<T>, i: number) => {
                const valueFormatterProps: AttributeFormatterProps = {
                    value: v.value,
                    locale,
                    format: formatContext.formats[type],
                };

                const indeterminate = v.part < 100;

                return (
                    <Box
                        key={i}
                        sx={{
                            display: 'flex',
                            color: indeterminate ? 'warning.main' : undefined,
                            p: 1,
                        }}
                    >
                        <div style={{
                            flexGrow: 1,
                        }}>
                            {formatter.formatValue(valueFormatterProps)}
                        </div>
                        <div style={{}}>
                            <IconButton
                                disabled={!indeterminate || readOnly || disabled}
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

            {addNew ? <>
                <AttributeWidget<T>
                    id={id}
                    name={name}
                    inputRef={inputRef}
                    type={type}
                    isRtl={false}
                    disabled={disabled}
                    required={false}
                    autoFocus={true}
                    value={newValue}
                    onChange={ChangeNewItemHandler}
                />
                <Button
                    sx={{mt: 2,}}
                    startIcon={<AddIcon/>}
                    variant="outlined"
                    disabled={readOnly || disabled}
                    color="primary"
                    onClick={addHandler}
                >
                    {t('form.attribute.collection.item_add', 'Add {{name}}', {
                        name,
                    })}
                </Button>
            </> : <>
                <Button
                    sx={{mt: 2,}}
                    startIcon={<AddIcon/>}
                    variant="outlined"
                    disabled={readOnly || disabled}
                    color="secondary"
                    onClick={newHandler}
                >
                    {t('form.attribute.collection.item_new', 'New {{name}}', {
                        name,
                    })}
                </Button>
            </>}
        </FormRow>
    );
}
