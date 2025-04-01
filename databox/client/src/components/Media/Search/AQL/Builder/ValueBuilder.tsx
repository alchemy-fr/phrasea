import {Box, IconButton, TextField, TextFieldProps} from "@mui/material";
import React, {ChangeEvent} from "react";
import {useTranslation} from "react-i18next";
import {BaseBuilderProps, QBCondition} from "./builderTypes.ts";
import {AQLLiteral, AQLOperator, AQLValue, ManyArgs, RawType} from "../aqlTypes.ts";
import {hasProp} from "../../../../../lib/utils.ts";
import {matchesFloat, matchesNumber} from "./builder.ts";
import AddIcon from "@mui/icons-material/Add";
import DeleteIcon from "@mui/icons-material/Delete";
import FieldBuilder, {FieldBuilderProps} from "./FieldBuilder.tsx";

type Props = {
    expression: BaseBuilderProps<QBCondition>['expression'];
    setExpression: BaseBuilderProps<QBCondition>['setExpression'];
    manyArgs: ManyArgs;
    rawType: RawType | undefined;
};

export default function ValueBuilder({
    manyArgs,
    expression,
    setExpression,
    rawType,
}: Props) {
    const {t} = useTranslation();

    const addValue = () => {
        setExpression(p => ({
            ...p,
            rightOperand: [
                ...(Array.isArray(p.rightOperand) ? p.rightOperand : [p.rightOperand ?? {literal: ''}]),
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

    const normValue = (value: string) => {
        let num: number | undefined = NaN;
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

    const fields: Omit<FieldBuilderProps, "rawType">[] = [];
    const manyArgsDefined = typeof manyArgs === 'number' || manyArgs === true;

    if (!manyArgsDefined) {
        fields.push({
            value: resolveValue(expression.rightOperand as AQLValue),
            name: 'value',
            label: t('search_condition.builder.value', 'Value'),
            onChange: e => {
                const v = normValue(e);

                setExpression(p => ({
                    ...p,
                    rightOperand: v
                }));
            },
        });
    } else {
        const argCount = ((expression.rightOperand ?? []) as AQLValue[]).length;
        for (let i = 0; i < argCount; i++) {
            fields.push({
                value: resolveValue((expression.rightOperand as AQLValue[])[i]),
                name: `value-${i}`,
                label: `${t('search_condition.builder.value', 'Value')} #${i + 1}`,
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
            <FieldBuilder
                {...f}
                rawType={rawType}
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
