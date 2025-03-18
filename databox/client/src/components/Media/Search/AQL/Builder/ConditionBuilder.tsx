import {RSelectWidget} from '@alchemy/react-form';
import React from "react";
import {FlexRow} from '@alchemy/phrasea-ui';
import {AttributeDefinitionIndex} from "../../../../AttributeEditor/types.ts";
import {useTranslation} from 'react-i18next';
import {IconButton, TextField} from "@mui/material";
import CloseIcon from "@mui/icons-material/Close";
import {AQLField} from "../aqlTypes.ts";
import {resolveValue} from "../query.ts";
import {QBCondition} from "./builderTypes.ts";

type Props = {
    definitionsIndex: AttributeDefinitionIndex;
    onChange: (handler: (prev: QBCondition) => QBCondition) => void;
    onRemove: (condition: QBCondition) => void;
    condition: QBCondition;
    operators: { value: string, label: string }[];
};

export default function ConditionBuilder({
    definitionsIndex,
    onChange,
    operators,
    condition,
    onRemove,
}: Props) {
    const {t} = useTranslation();

    return <FlexRow sx={{
        gap: 2,
    }}>
        <div>
            <RSelectWidget
                placeholder={t('search_condition.builder.field', 'Field')}
                name={'field'}
                onChange={newValue => {
                    onChange(p => ({
                        ...p,
                        leftOperand: {
                            field: newValue?.value ?? '',
                        }
                    }));
                }}
                value={(condition.leftOperand as AQLField).field as any}
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
                value={condition.operator as any}
                onChange={newValue => {
                    onChange(p => ({
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
                value={resolveValue(condition.rightOperand)}
                onChange={e => {
                    const num = parseInt(e.target.value, 10);
                    const v = isNaN(num) ? {
                        literal: e.target.value,
                    } : num; // TODO handle boolean

                    onChange(p => ({
                        ...p,
                        rightOperand: v
                    }));
                }}
            />
        </div>
        <div>
            <IconButton
                onClick={() => {
                    onRemove(condition);
                }}
            >
                <CloseIcon/>
            </IconButton>
        </div>
    </FlexRow>
}
