import React from "react";
import {FlexRow} from '@alchemy/phrasea-ui';
import {Box, IconButton} from "@mui/material";
import {BaseBuilderProps, QBAndOrExpression, QBExpression} from "./builderTypes.ts";
import {emptyCondition, removeExpression} from "./builder.ts";
import ExpressionBuilder from "./ExpressionBuilder.tsx";
import DeleteIcon from "@mui/icons-material/Delete";
import AddExpressionRow from "./AddExpressionRow.tsx";
import {StateSetterHandler} from "../../../../../types.ts";
import {RSelectWidget} from '@alchemy/react-form';

export default function AndOrOrExpressionBuilder({
    definitionsIndex, expression, setExpression, onRemove,
    operators
}: BaseBuilderProps<QBAndOrExpression>) {


    return <Box sx={theme => ({
        border: `1px solid ${theme.palette.divider}`,
        p: 2,
        my: 1,
    })}>
        <FlexRow>
            <div>
                <RSelectWidget
                    name={'operator'}
                    required={true}
                    onChange={newValue => {
                        setExpression(p => ({
                            ...p,
                            operator: (newValue?.value ?? 'AND') as QBAndOrExpression['operator'],
                        }));
                    }}
                    value={expression.operator as any}
                    options={[
                        {
                            value: 'OR',
                            label: 'OR',
                        },
                        {
                            value: 'AND',
                            label: 'AND',
                        },
                    ]}
                />
            </div>
            <IconButton
                onClick={() => {
                    onRemove(expression);
                }}
            >
                <DeleteIcon/>
            </IconButton>
        </FlexRow>

        {expression.conditions.map((c, index) => {
            return <ExpressionBuilder
                key={index}
                operators={operators}
                definitionsIndex={definitionsIndex}
                expression={c}
                setExpression={((handler) => {
                    setExpression((p) => ({
                        ...p,
                        conditions: p.conditions.map((c2, i2) => {
                            if (i2 === index) {
                                return handler(c);
                            }
                            return c2;
                        }).filter(c => null !== c),
                    }));
                })}
                onRemove={(expr) => {
                    setExpression(p => (removeExpression(p, expr) || {...emptyCondition}) as any)
                }}
            />
        })}

        <AddExpressionRow setExpression={setExpression as StateSetterHandler<QBExpression>}/>
    </Box>
}
