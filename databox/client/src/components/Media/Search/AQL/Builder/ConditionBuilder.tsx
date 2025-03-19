import {RSelectWidget} from '@alchemy/react-form';
import React from "react";
import {FlexRow} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import {IconButton, TextField} from "@mui/material";
import {AQLField} from "../aqlTypes.ts";
import {resolveValue} from "../query.ts";
import {BaseBuilderProps, QBCondition} from "./builderTypes.ts";
import DeleteIcon from "@mui/icons-material/Delete";

export default function ConditionBuilder({
    definitionsIndex,
    setExpression,
    operators,
    expression,
    onRemove,
}: BaseBuilderProps<QBCondition>) {
    const {t} = useTranslation();

    return <FlexRow sx={{
        gap: 2,
    }}>
        <div>
            <RSelectWidget
                placeholder={t('search_condition.builder.field', 'Field')}
                name={'field'}
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
                }))}
            />
        </div>
        <div>
            <RSelectWidget
                placeholder={t('search_condition.builder.operator', 'Operator')}
                name={'operator'}
                options={operators}
                value={expression.operator as any}
                onChange={newValue => {
                    setExpression(p => ({
                        ...p,
                        operator: newValue?.value ?? '',
                    }));
                }}
            />
        </div>
        <div>
            <TextField
                placeholder={t('search_condition.builder.value', 'Value')}
                name={'value'}
                value={resolveValue(expression.rightOperand)}
                onChange={e => {
                    const num = parseInt(e.target.value, 10);
                    const v = isNaN(num) ? {
                        literal: e.target.value,
                    } : num; // TODO handle boolean

                    setExpression(p => ({
                        ...p,
                        rightOperand: v
                    }));
                }}
            />
        </div>
        <div>
            <IconButton
                onClick={() => {
                    onRemove(expression);
                }}
            >
                <DeleteIcon/>
            </IconButton>
        </div>
    </FlexRow>
}
