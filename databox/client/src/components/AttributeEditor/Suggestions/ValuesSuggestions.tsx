import {SuggestionTabProps} from '../types.ts';
import {
    Box,
    Checkbox,
    InputLabel,
    ListItem,
    ListItemButton, Stack,
} from '@mui/material';
import React from 'react';
import {useTranslation} from 'react-i18next';
import PartPercentage, {partPercentageClassName} from '../PartPercentage.tsx';

type Stats = Record<string, number>;

type Value<T> = {
    label: string;
    value: T;
    part: number;
};
type Props<T> = {} & SuggestionTabProps<T>;

export default function ValuesSuggestions<T>({
    valueContainer,
    setAttributeValue,
    definition,
    locale,
    toKey,
    subSelection,
}: Props<T>) {
    const {t} = useTranslation();
    const [useOriginal, setUseOriginal] = React.useState(false);
    const [displayPercents, setDisplayPercents] = React.useState(true);

    const {distinctValues, lengthRef} = React.useMemo<{
        distinctValues: Value<T>[],
        lengthRef: number;
    }>(() => {
        const stats: Stats = {};
        const tmpValues = (
            (!useOriginal
                ? valueContainer.values
                : valueContainer.originalValues) ?? []
        ).map(tr => tr[locale]);
        const values = definition.multiple
            ? (tmpValues.flat() as T[])
            : tmpValues;
        const lengthRef = definition.multiple
            ? subSelection.length
            : tmpValues.length;
        if (!definition.multiple && values.length === 0) {
            values.push(undefined);
        }

        const norm = (v: T) => toKey(definition.fieldType, v);
        const sortFn = (a: Value<T>, b: Value<T>) => {
            if (a.part === b.part) {
                return a.label
                    ? b.label
                        ? a.label.localeCompare(b.label)
                        : 1
                    : -1;
            }

            return (b.part ?? 0) - (a.part ?? 0);
        };

        const distinctValues = values
            .map((v: T): Value<T> => {
                const key = norm(v);

                return {
                    label: key,
                    value: v,
                    part: 0,
                };
            })
            .map(v => {
                stats[v.label] ??= 0;
                stats[v.label]++;

                return v;
            })
            .map(
                (v: Value<T>): Value<T> => ({
                    ...v,
                    part:
                        Math.min(100, Math.round(
                            (stats[v.label] / (lengthRef || 1)) * 10000
                        ) / 100),
                })
            )
            .filter(
                (value, index, array) =>
                    array.findIndex(v => v.label === value.label) === index
            )
            .sort(sortFn);
        return {distinctValues, lengthRef};
    }, [valueContainer, useOriginal, definition, locale]);

    const emptyValueClassName = 'empty-val';
    const labelWrapperClassName = 'label-wr';
    const labelClassName = 'label-val';

    return (
        <Box
            sx={{
                [`.${labelWrapperClassName}`]: {
                    width: '100%',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                },
                [`.${labelClassName}`]: {
                    flexShrink: 1,
                    whiteSpace: 'wrap',
                    wordBreak: 'break-word',
                },
                [`.${emptyValueClassName}`]: {
                    fontStyle: 'italic',
                    color: 'secondary.main',
                },
                [`.${partPercentageClassName}`]: {
                    flexShrink: 0,
                    flexGrow: 0,
                },
            }}
        >
            <div>
                <Stack direction={'row'}>
                    <InputLabel>
                        <Checkbox
                            checked={useOriginal}
                            onChange={e => setUseOriginal(e.target.checked)}
                        />
                        {t(
                            'attribute_editor.suggestions.originalValues.label',
                            'Compare original values'
                        )}
                    </InputLabel>
                    <InputLabel>
                        <Checkbox
                            checked={displayPercents}
                            onChange={e => setDisplayPercents(e.target.checked)}
                        />
                        {t(
                            'attribute_editor.suggestions.display_percents.label',
                            'Percentages',
                        )}
                    </InputLabel>
                </Stack>
            </div>
            {distinctValues.map((v: Value<T>, index: number) => {
                return (
                    <ListItem key={index} disablePadding>
                        <ListItemButton
                            onClick={() =>
                                setAttributeValue(v.value, {
                                    updateInput: true,
                                    add: definition.multiple,
                                })
                            }
                        >
                            <div className={labelWrapperClassName}>
                                <div
                                    className={`${labelClassName} ${!v.label ? emptyValueClassName : ''}`}
                                >
                                    {v.label ||
                                        t(
                                            'attribute_editor.suggestions.no_value',
                                            '- empty -'
                                        )}
                                </div>
                                <PartPercentage
                                    part={v.part}
                                    width={110}
                                    displayPercents={displayPercents}
                                    total={lengthRef}
                                />
                            </div>
                        </ListItemButton>
                    </ListItem>
                );
            })}
        </Box>
    );
}
