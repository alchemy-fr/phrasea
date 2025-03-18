import React from "react";
import {FlexRow} from '@alchemy/phrasea-ui';
import {AttributeDefinitionIndex} from "../../../../AttributeEditor/types.ts";
import {useTranslation} from 'react-i18next';
import {IconButton} from "@mui/material";
import ConditionBuilder from "./ConditionBuilder.tsx";
import AddIcon from "@mui/icons-material/Add";
import {QBCondition} from "./builderTypes.ts";

type Props = {
    definitionsIndex: AttributeDefinitionIndex;
};

export default function ConditionsBuilder({definitionsIndex}: Props) {
    const {t} = useTranslation();
    const emptyCondition: QBCondition = {
        leftOperand: {
            field: '',
        },
        operator: '',
        rightOperand: {literal: ''},
    };

    const [conditions, setConditions] = React.useState<QBCondition[]>([
        emptyCondition
    ]);

    const operators = [
        {
            value: '=',
            label: t('search_condition.builder.operator.equals', 'Equals (=)'),
        },
        {
            value: '!=',
            label: t('search_condition.builder.operator.not_equals', 'Not equals (!=)'),
        },
        {
            value: '>',
            label: t('search_condition.builder.operator.greater_than', 'Greater than (>)'),
        },
        {
            value: '>=',
            label: t('search_condition.builder.operator.greater_than_or_equals', 'Greater than or equals (>=)'),
        },
        {
            value: '<',
            label: t('search_condition.builder.operator.less_than', 'Less than (<)'),
        },
        {
            value: '<=',
            label: t('search_condition.builder.operator.less_than_or_equals', 'Less than or equals (<=)'),
        },
        {
            value: 'CONTAINS',
            label: t('search_condition.builder.operator.contains', 'Contains'),
        },
        {
            value: 'MATCHES',
            label: t('search_condition.builder.operator.matches', 'Matches'),
        },
    ];

    const onRemove = (condition: QBCondition) => {
        setConditions(p => {
            return p.filter(c2 => c2 !== condition);
        });

    };

    return <>
        {conditions.map((c, index) => {
            return <ConditionBuilder
                key={index}
                operators={operators}
                definitionsIndex={definitionsIndex}
                condition={c}
                onChange={(handler) => {
                    setConditions(p => {
                        return p.map((c2, i2) => {
                            if (i2 === index) {
                                return handler(c);
                            }
                            return c2;
                        });
                    });
                }}
                onRemove={onRemove}
            />
        })}
        <FlexRow>
            <IconButton
                onClick={() => {
                    setConditions(p => {
                        return [
                            ...p,
                            {
                                ...emptyCondition,
                            }
                        ];
                    });
                }}
            >
                <AddIcon/>
            </IconButton>
        </FlexRow>
    </>
}
