import {SuggestionTabProps} from '../types.ts';
import {Box, Checkbox, InputLabel, ListItem, ListItemButton, Menu, Stack,} from '@mui/material';
import React, {MouseEventHandler} from 'react';
import {useTranslation} from 'react-i18next';
import PartPercentage, {partPercentageClassName} from '../PartPercentage.tsx';
import {getAttributeType} from "../../Media/Asset/Attribute/types";
import MenuItem from "@mui/material/MenuItem";

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
    const [displayPercents, setDisplayPercents] = React.useState(false);
    const [anchorEl, setAnchorEl] = React.useState<null | {
        anchor: HTMLElement,
        value: T;
    }>();
    const open = Boolean(anchorEl);
    const handleClose = () => {
        setAnchorEl(null);
    };
    const toggleDisplayPercents = React.useCallback<MouseEventHandler<HTMLDivElement>>((e) => {
        e.stopPropagation();
        setDisplayPercents(p => !p);
    }, []);

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

    const widget = getAttributeType(definition.fieldType);

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
                </Stack>
            </div>
            <Menu
                id="context-menu"
                anchorEl={anchorEl?.anchor}
                open={open}
                onClose={handleClose}
                MenuListProps={{
                    'aria-labelledby': 'basic-button',
                }}
            >
                <MenuItem onClick={() => {
                    setAttributeValue(anchorEl!.value, {
                        updateInput: true,
                        add: definition.multiple,
                    });
                    handleClose();
                }}>
                    {t('attribute_editor.suggestions.menu.apply', 'Apply to selected assets')}
                </MenuItem>
                <MenuItem onClick={handleClose}>
                    {t('attribute_editor.suggestions.menu.select_with_value', 'Select assets having this value')}
                </MenuItem>
            </Menu>
            {distinctValues.map((v: Value<T>, index: number) => {
                return (
                    <ListItem key={index} disablePadding>
                        <ListItemButton
                            onClick={(e) => {
                                setAnchorEl({
                                    anchor: e.currentTarget,
                                    value: v.value,
                                });
                            }}
                        >
                            <div className={labelWrapperClassName}>
                                <div
                                    className={`${labelClassName} ${!v.label ? emptyValueClassName : ''}`}
                                >
                                    {v.value ? widget.formatValue({
                                            value: v.value,
                                        }) :
                                        t(
                                            'attribute_editor.suggestions.no_value',
                                            '- empty -'
                                        )}
                                </div>
                                <div
                                    onClick={toggleDisplayPercents}
                                >
                                    <PartPercentage
                                        part={v.part}
                                        width={110}
                                        displayPercents={displayPercents}
                                        total={lengthRef}
                                    />
                                </div>
                            </div>
                        </ListItemButton>
                    </ListItem>
                );
            })}
        </Box>
    );
}
