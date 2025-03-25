import {RSelectWidget, SelectOption} from '@alchemy/react-form';
import React from "react";
import {useTranslation} from 'react-i18next';
import {IconButton, useTheme} from "@mui/material";
import {AQLField, AQLOperator, AQLValue, ManyArgs} from "../aqlTypes.ts";
import {BaseBuilderProps, QBCondition} from "./builderTypes.ts";
import DeleteIcon from "@mui/icons-material/Delete";
import ValueBuilder from "./ValueBuilder.tsx";
import Grid from "@mui/material/Unstable_Grid2";
import {alpha} from "@mui/material/styles";
import {StylesConfig} from "react-select";


export default function ConditionBuilder({
    definitionsIndex,
    setExpression,
    operators,
    expression,
    onRemove,
}: BaseBuilderProps<QBCondition>) {
    const {t} = useTranslation();
    const theme = useTheme();

    const fieldStyles: StylesConfig<{builtIn?: boolean} & SelectOption, false> = {
        option: (base, {isDisabled, isFocused, isSelected, data}) => ({
            ...base,
            backgroundColor: isDisabled
                ? undefined
                : isSelected
                    ? theme.palette.primary.main
                    : isFocused
                        ? alpha(theme.palette.primary.main, 0.1)
                        : undefined,
            ...(data.builtIn ? {
                fontWeight: 700,
            } : {}),
        }),
    };

    const manyArgs: ManyArgs = operators.find(o => o.value === expression.operator)?.manyArgs;

    return <Grid container spacing={1}>
        <Grid xs={4}>
            <RSelectWidget
                placeholder={t('search_condition.builder.field', 'Field')}
                name={'field'}
                styles={fieldStyles}
                onChange={newValue => {
                    setExpression(p => ({
                        ...p,
                        leftOperand: {
                            field: newValue?.value ?? '',
                        }
                    }));
                }}
                value={(expression.leftOperand as AQLField).field as any}
                options={Object.entries(definitionsIndex).map(([_slug, def]) => ({
                    value: def.slug,
                    label: def.name,
                    builtIn: def.builtIn,
                })).sort((a, b) => ((b.builtIn ? 1 : 0) - (a.builtIn ? 1 : 0)) * 1000 + a.label.localeCompare(b.label))}
            />
        </Grid>
        <Grid xs={3}>
            <RSelectWidget
                required={true}
                placeholder={t('search_condition.builder.operator', 'Operator')}
                name={'operator'}
                options={operators}
                value={expression.operator as any}
                onChange={newValue => {
                    setExpression(p => {
                        const op = (newValue?.value ?? '') as AQLOperator;
                        const manyArgs = op && operators.find(o => o.value === op)?.manyArgs;
                        let rightOperand = p.rightOperand;

                        if (manyArgs && !Array.isArray(p.rightOperand)) {
                            rightOperand = [rightOperand as AQLValue];
                        } else if (!manyArgs && Array.isArray(p.rightOperand)) {
                            rightOperand = (rightOperand as AQLValue[])[0];
                        }

                        if (typeof manyArgs === 'number') {
                            rightOperand = (rightOperand as AQLValue[]).slice(0, manyArgs);
                            if (rightOperand.length < manyArgs) {
                                rightOperand = rightOperand.concat(new Array(manyArgs - rightOperand.length).fill({literal : ''}));
                            }
                        }

                        return ({
                            ...p,
                            operator: op,
                            rightOperand,
                        });
                    });
                }}
            />
        </Grid>
        <Grid xs={4}>
            <ValueBuilder
                manyArgs={manyArgs}
                expression={expression}
                setExpression={setExpression}
            />
        </Grid>
        <Grid xs={1}>
            <IconButton
                onClick={() => {
                    onRemove(expression);
                }}
            >
                <DeleteIcon/>
            </IconButton>
        </Grid>
    </Grid>
}
