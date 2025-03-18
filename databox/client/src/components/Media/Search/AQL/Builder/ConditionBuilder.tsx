import {RSelectWidget} from '@alchemy/react-form';
import React from "react";
import {FlexRow} from '@alchemy/phrasea-ui';
import {AttributeDefinitionIndex} from "../../../../AttributeEditor/types.ts";
import {useTranslation} from 'react-i18next';
import {IconButton, TextField} from "@mui/material";
import CloseIcon from "@mui/icons-material/Close";
import {AQLCondition, AQLField} from "../aqlTypes.ts";

type Props = {
    definitionsIndex: AttributeDefinitionIndex;
    onChange: (prev: AQLCondition) => AQLCondition;
    condition: AQLCondition;
    operators: { value: string, label: string }[];
};

export default function ConditionBuilder({
    definitionsIndex,
    onChange,
    operators,
    condition,
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
                value={(condition.leftOperand as AQLField).field}
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
                value={condition.operator}
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
                    console.log('add condition');
                }}
            >
                <CloseIcon/>
            </IconButton>
        </div>
    </FlexRow>
}
