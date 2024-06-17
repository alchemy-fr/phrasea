import {SuggestionTabProps} from "../types.ts";
import {Box, Checkbox, InputLabel, ListItem, ListItemButton} from "@mui/material";
import React from "react";
import {useTranslation} from 'react-i18next';

type Props = {} & SuggestionTabProps;

type Stats = Record<string, number>;
type Value = {
    label: string;
    part: number;
};

export default function ValuesSuggestions({
    valueContainer,
    setAttributeValue,
}: Props) {
    const partClassName = 'value-suggest-part';
    const {t} = useTranslation();
    const [useOriginal, setUseOriginal] = React.useState(false);

    const distinctValues = React.useMemo<Value[]>(() => {
        const stats: Stats = {};

        const values = (!useOriginal ? valueContainer.values : valueContainer.originalValues) ?? [];

        const norm = (s: any): string => s ? (typeof s === 'string' ? s : '') : '';
        const sortFn = (a: Value, b: Value) => {
            if (a.part === b.part) {
                return a.label ? (b.label ? a.label.localeCompare(b.label) : 1) : -1;
            }

            return (b.part ?? 0) - (a.part ?? 0);
        };

        return (values
            .map(norm) as string[])
            .map(v => {
                stats[v] ??= 0;
                stats[v]++;

                return v;
            })
            .filter((value, index, array) => array.indexOf(value) === index)
            .map((v: string): Value => ({
                label: v,
                part: Math.round(stats[v] / values.length * 10000) / 100,
            }))
            .sort(sortFn);

    }, [valueContainer, useOriginal]);

    return <Box sx={{
        [`.${partClassName}`]: {
            color: 'info.main',
            pl: 2,
        }
    }}>
        <div>
            <InputLabel>
                <Checkbox
                    checked={useOriginal}
                    onChange={e => setUseOriginal(e.target.checked)}
                />
                {t('attribute_editor.suggestions.originalValues.label', 'Display original values')}
            </InputLabel>
        </div>
        {distinctValues.map((v: Value, index) => {
            return <ListItem
                key={index}
                disablePadding
            >
                <ListItemButton
                    onClick={() => setAttributeValue(v.label, true)}
                    sx={!v.label ? {
                        fontStyle: 'italic',
                        color: 'secondary.main',
                    } : undefined}
                >
                    {v.label || t('attribute_editor.suggestions.no_value', '- empty -')}
                    {v.part ? <span className={partClassName}>
                        {v.part}%
                    </span> : ''}
                </ListItemButton>
            </ListItem>
        })}
    </Box>
}
