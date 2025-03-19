import {AQLCondition, AQLValue} from "../aqlTypes.ts";
import {AttributeDefinitionIndex} from "../../../../AttributeEditor/types.ts";

export type QBExpression = QBAndOrExpression | QBCondition;

export type QBAndOrExpression = {
    operator?: 'AND' | 'OR';
    conditions: QBExpression[];
}

export type QBCondition = {
    operator: string;
    rightOperand: AQLValue;
} & Omit<AQLCondition, 'operator' | 'rightOperand'>;

export type BaseBuilderProps<T> = {
    operators: { value: string, label: string }[];
    definitionsIndex: AttributeDefinitionIndex;
    setExpression: (handler: (prev: T) => T) => void;
    expression: T;
    onRemove: RemoveExpressionHandler<T>;
}

export type RemoveExpressionHandler<T> = (expression: T) => void;
