import React from "react";
import {FlexRow} from '@alchemy/phrasea-ui';
import {Box, IconButton} from "@mui/material";
import AddIcon from "@mui/icons-material/Add";
import {BaseBuilderProps, QBAndOrExpression, QBExpression, RemoveExpressionHandler} from "./builderTypes.ts";
import {addExpression, removeExpression} from "./builder.ts";
import ExpressionBuilder from "./ExpressionBuilder.tsx";
import DeleteIcon from "@mui/icons-material/Delete";

export default function AndOrOrExpressionBuilder({
    definitionsIndex, expression, setExpression, onRemove,
    operators
}: BaseBuilderProps<QBAndOrExpression>) {
    return <Box sx={{
        border: `1px solid #ccc`,
        p: 2,
        my: 1,
    }}>
        <FlexRow>
            <div>
                AndOrOrExpressionBuilder <b>{expression.operator}</b>
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
                        }),
                    }));
                })}
                onRemove={(expr) => {
                    setExpression(p => removeExpression(p, expr, onRemove as RemoveExpressionHandler<QBExpression>) as QBAndOrExpression);
                }}
            />
        })}

        <FlexRow>
            <IconButton
                onClick={() => {
                    setExpression(p => {
                        return addExpression(p);
                    });
                }}
            >
                <AddIcon/>
            </IconButton>
        </FlexRow>
    </Box>
}
