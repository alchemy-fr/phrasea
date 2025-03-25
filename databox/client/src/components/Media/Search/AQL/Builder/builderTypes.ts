import {AQLCondition, AQLOperator, AQLValue, ManyArgs} from "../aqlTypes.ts";
import {AttributeDefinitionIndex} from "../../../../AttributeEditor/types.ts";

export type QBExpression = QBAndOrExpression | QBCondition;

export type QBAndOrExpression = {
    operator?: 'AND' | 'OR';
    conditions: QBExpression[];
}

export type QBCondition = {
    operator: AQLOperator;
    rightOperand: AQLValue | AQLValue[];
} & Omit<AQLCondition, 'operator' | 'rightOperand'>;

export type OperatorChoice = {
    value: AQLOperator;
    label: string;
    manyArgs?: ManyArgs;
}

export type BaseBuilderProps<T> = {
    operators: OperatorChoice[];
    definitionsIndex: AttributeDefinitionIndex;
    setExpression: (handler: (prev: T) => T) => void;
    expression: T;
    onRemove: RemoveExpressionHandler<T>;
}

export type RemoveExpressionHandler<T> = (expression: T) => void;
