import {
    AQLAndOrOperator,
    AQLCondition,
    AQLOperator,
    AQLValueOrExpression,
    ArgNames,
    ManyArgs,
    RawType,
} from '../aqlTypes.ts';
import {AttributeDefinitionIndex} from '../../../../AttributeEditor/types.ts';

export type QBExpression = QBAndOrExpression | QBCondition;

export type QBAndOrExpression = {
    operator?: AQLAndOrOperator;
    conditions: QBExpression[];
};

export type QBCondition = {
    operator: AQLOperator;
    rightOperand: AQLValueOrExpression | AQLValueOrExpression[] | undefined;
} & Omit<AQLCondition, 'operator' | 'rightOperand'>;

export type BaseBuilderProps<T> = {
    operators: OperatorChoice[];
    definitionsIndex: AttributeDefinitionIndex;
    setExpression: (handler: (prev: T) => T) => void;
    expression: T;
    onRemove: RemoveExpressionHandler<T>;
};

export type RemoveExpressionHandler<T> = (expression: T) => void;

export type OperatorChoice = {
    value: AQLOperator;
    label: string;
    manyArgs?: ManyArgs;
    argNames?: ArgNames;
    supportedTypes?: RawType[];
};
