import {Box, IconButton, TextField, TextFieldProps} from "@mui/material";
import React, {ChangeEvent} from "react";
import {useTranslation} from "react-i18next";
import {BaseBuilderProps, QBCondition} from "./builderTypes.ts";
import {AQLLiteral, AQLValue, ManyArgs} from "../aqlTypes.ts";
import {hasProp} from "../../../../../lib/utils.ts";
import {matchesFloat, matchesNumber} from "./builder.ts";
import AddIcon from "@mui/icons-material/Add";
import DeleteIcon from "@mui/icons-material/Delete";

type Props = {
    expression: BaseBuilderProps<QBCondition>['expression'];
    setExpression: BaseBuilderProps<QBCondition>['setExpression'];
    manyArgs: ManyArgs;
};

export default function ValueBuilder({
    manyArgs,
    expression,
    setExpression,
}: Props) {
    const {t} = useTranslation();

    const addValue = () => {
        setExpression(p => ({
            ...p,
            rightOperand: [
                ...(Array.isArray(p.rightOperand) ? p.rightOperand : [p.rightOperand]),
                {literal: ''},
            ]
        }));
    }

    const removeValue = (index: number) => {
        setExpression(p => ({
            ...p,
            rightOperand: (p.rightOperand as AQLValue[]).filter((_, i) => i !== index)
        }));
    }

    const normValue = (e: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        let num: number | undefined = NaN;
        const value = e.target.value;
        if (matchesNumber(value)) {
            num = parseInt(value, 10);
        } else if (matchesFloat(value)) {
            num = parseFloat(value);
        } else if (value.length >= 2 && value[0] === '"' && value[value.length - 1] === '"') {
            // User casted string
            return {
                literal: value.slice(1, value.length - 1),
            };
        }

        return isNaN(num) ? {
            literal: value,
        } : num;
    }

    const fields: TextFieldProps[] = [];

    if (!manyArgs) {
        fields.push({
            value: resolveValue(expression.rightOperand as AQLValue),
            name: 'value',
            placeholder: t('search_condition.builder.value', 'Value'),
            onChange: e => {
                const v = normValue(e);

                setExpression(p => ({
                    ...p,
                    rightOperand: v
                }));
            },
        });
    } else {
        const argCount = (expression.rightOperand as AQLValue[]).length;
        for (let i = 0; i < argCount; i++) {
            fields.push({
                value: resolveValue((expression.rightOperand as AQLValue[])[i]),
                name: `value-${i}`,
                placeholder: `${t('search_condition.builder.value', 'Value')} #${i + 1}`,
                onChange: e => {
                    const v = normValue(e);

                    setExpression(p => ({
                        ...p,
                        rightOperand: (p.rightOperand as AQLValue[]).map((r, index) => {
                            if (index === i) {
                                return v;
                            }
                            return r;
                        })
                    }));
                },
            });
        }
    }

    return <>
        {fields.map((f, index) => (<Box
            key={index}
            sx={{
                display: 'flex',
                gap: 1,
                alignItems: 'center',
            }}
        >
            <TextField
                {...f}
                fullWidth={true}
            />
            {manyArgs === true && <div><IconButton
                onClick={() => removeValue(index)}

            >
                <DeleteIcon/>
            </IconButton></div>}
        </Box>))}
        {manyArgs === true && <IconButton
            onClick={addValue}

        >
            <AddIcon/>
        </IconButton>}
    </>
}

function resolveValue(value: AQLValue): string {
    if (typeof value === 'object' && hasProp<AQLLiteral>(value, 'literal')) {
        if (matchesNumber(value.literal)) {
            return `"${value.literal}"`;
        }

        return value.literal;
    }

    if (!value) {
        return '';
    }

    return value.toString();
}
