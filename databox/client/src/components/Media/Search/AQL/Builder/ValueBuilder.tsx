import {Box, IconButton} from "@mui/material";
import React from "react";
import {useTranslation} from "react-i18next";
import {BaseBuilderProps, QBCondition} from "./builderTypes.ts";
import {AQLCondition, AQLLiteral, AQLValue, AQLValueOrExpression, ArgNames, ManyArgs, RawType} from "../aqlTypes.ts";
import {hasProp} from "../../../../../lib/utils.ts";
import {matchesFloat, matchesNumber} from "./builder.ts";
import AddIcon from "@mui/icons-material/Add";
import DeleteIcon from "@mui/icons-material/Delete";
import FieldBuilder, {FieldBuilderProps} from "./FieldBuilder.tsx";
import {parseAQLQuery} from "../AQL.ts";
import {valueToString} from "../query.ts";

type Props = {
    expression: BaseBuilderProps<QBCondition>['expression'];
    setExpression: BaseBuilderProps<QBCondition>['setExpression'];
    manyArgs: ManyArgs;
    argNames: ArgNames;
    rawType: RawType | undefined;
};

export default function ValueBuilder({
    manyArgs,
    argNames,
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

    const normValue = (value: string): AQLValueOrExpression => {
        if (value.startsWith('=')) {
            const result = parseAQLQuery(`f = ${value.slice(1)}`);
            if (!result) {
                return value as any;
            }
            return (result.expression as AQLCondition).rightOperand as AQLValueOrExpression;
        }

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
            value: resolveValue(expression.rightOperand as AQLValueOrExpression),
            name: 'value',
            label: argNames?.[0] ?? t('search_condition.builder.value', 'Value'),
            onChange: e => {
                const v = normValue(e);

                setExpression(p => ({
                    ...p,
                    rightOperand: v
                }));
            },
        });
    } else {
        const argCount = ((expression.rightOperand ?? []) as AQLValueOrExpression[]).length;
        for (let i = 0; i < argCount; i++) {
            fields.push({
                value: resolveValue((expression.rightOperand as AQLValueOrExpression[])[i]),
                name: `value-${i}`,
                label: argNames?.[i] ?? `${t('search_condition.builder.value', 'Value')} #${i + 1}`,
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

function resolveValue(value: AQLValueOrExpression): string {
    if (typeof value === 'object') {
        if (hasProp<AQLLiteral>(value, 'literal')) {
            if (matchesNumber(value.literal)) {
                return `"${value.literal}"`;
            }

            return value.literal;
        } else {
            return `=${valueToString(value)}`;
        }
    }

    if (!value) {
        return '';
    }

    return value.toString();
}
