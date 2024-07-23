import React, {useContext, useEffect, useState} from 'react';
import {Box, Button, IconButton} from '@mui/material';
import {FormRow} from '@alchemy/react-form';
import {useTranslation} from 'react-i18next';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import {
    MultiValueIndex,
    MultiValueValue,
    SelectedValue,
    SetAttributeValue,
    CreateToKeyFunc,
    Values,
} from './types.ts';
import {getAttributeType} from '../Media/Asset/Attribute/types';
import {AttributeFormatterProps} from '../Media/Asset/Attribute/types/types';
import {AttributeFormatContext} from '../Media/Asset/Attribute/Format/AttributeFormatContext.ts';
import AttributeWidget from './AttributeWidget.tsx';
import classNames from 'classnames';
import {AttributeDefinition, StateSetter} from '../../types.ts';
import {createWidgetOptionsFromDefinition} from "../Media/Asset/Attribute/AttributeWidget.tsx";

type Props<T> = {
    attributeDefinition: AttributeDefinition;
    valueContainer: Values;
    disabled: boolean;
    indeterminate?: boolean;
    readOnly?: boolean;
    locale: string;
    createToKey: CreateToKeyFunc<T>;
    setAttributeValue: SetAttributeValue<T>;
    selectedValue: SelectedValue | undefined;
    setSelectedValue: StateSetter<SelectedValue<T> | undefined>;
};

export default function MultiAttributeRow<T>({
    valueContainer,
    disabled,
    attributeDefinition,
    readOnly,
    locale,
    createToKey,
    setAttributeValue,
    selectedValue,
    setSelectedValue,
}: Props<T>) {
    const {id, name, fieldType: type} = attributeDefinition;

    const inputRef = React.useRef<HTMLInputElement | null>(null);
    const {t} = useTranslation();
    const formatContext = useContext(AttributeFormatContext);
    const [newValue, setNewValue] = React.useState<T | undefined>();
    const definitionRef = React.useRef<string>(id);

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

    React.useEffect(() => {
        const onEnter = (e: KeyboardEvent) => {
            if (e.key === 'Enter' && newValue) {
                addHandler();
            }
        };
        window.addEventListener('keydown', onEnter);

        return () => {
            window.removeEventListener('keydown', onEnter);
        };
    }, [addHandler]);

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
        const toKey = createToKey(attributeDefinition.fieldType);

        valueContainer.values.forEach(translations => {
            const values = translations[locale];

            values?.forEach((v: T) => {
                const k = toKey(v);

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
    }, [valueContainer, locale, attributeDefinition]);

    const [values, setValues] = useState<MultiValueValue<T>[]>(computed ?? []);

    const finalValues = definitionRef.current !== id ? computed ?? [] : values;
    const finalNewValue = definitionRef.current !== id ? undefined : newValue;
    const finalSelectedValue =
        definitionRef.current !== id ? undefined : selectedValue;

    useEffect(() => {
        setValues(computed ?? []);
        setNewValue(undefined);
        definitionRef.current = id;
    }, [computed, id]);

    React.useEffect(() => {
        setSelectedValue(undefined);
    }, []);

    const formatter = getAttributeType(type);
    const itemClassName = 'item';

    return (
        <FormRow
            sx={{
                [`.${itemClassName}`]: {
                    display: 'flex',
                    cursor: 'pointer',
                    alignItems: 'center',
                    p: 1,
                },
                [`.${itemClassName}.selected`]: {
                    bgcolor: 'divider',
                },
                '.vw': {
                    flexGrow: 1,
                },
                [`.${itemClassName}.indeterminate .vw`]: {
                    opacity: 0.5,
                },
            }}
        >
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
                options={createWidgetOptionsFromDefinition(attributeDefinition)}
            />
            <Button
                sx={{mb: 2}}
                startIcon={<AddIcon />}
                variant="outlined"
                disabled={readOnly || disabled || !newValue}
                color="primary"
                onClick={addHandler}
            >
                {t('form.attribute.collection.item_add', 'Add {{name}}', {
                    name,
                })}
            </Button>

            {finalValues.map((v: MultiValueValue<T>, i: number) => {
                const valueFormatterProps: AttributeFormatterProps = {
                    value: v.value,
                    locale,
                    format: formatContext.formats[type],
                };

                const indeterminate = v.part < 100;
                const isSelected = finalSelectedValue?.key === v.key;

                return (
                    <Box
                        key={i}
                        onClick={() =>
                            setSelectedValue(p =>
                                p && p.key === v.key
                                    ? undefined
                                    : {
                                          value: v.value,
                                          key: v.key,
                                      }
                            )
                        }
                        className={classNames({
                            [itemClassName]: true,
                            indeterminate,
                            selected: isSelected,
                        })}
                    >
                        <div className={'vw'}>
                            {formatter.formatValue(valueFormatterProps)}
                        </div>
                        <div style={{}}>
                            <IconButton
                                disabled={
                                    !indeterminate || readOnly || disabled
                                }
                                onClick={e => {
                                    e.stopPropagation();
                                    addValueHandler(v.value);
                                }}
                                color="success"
                            >
                                <AddIcon />
                            </IconButton>
                            <IconButton
                                disabled={readOnly || disabled}
                                onClick={e => {
                                    e.stopPropagation();
                                    removeValueHandler(v.value);
                                }}
                                color="error"
                            >
                                <DeleteIcon />
                            </IconButton>
                        </div>
                    </Box>
                );
            })}
        </FormRow>
    );
}
