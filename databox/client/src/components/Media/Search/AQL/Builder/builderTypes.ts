import {AQLCondition, AQLValue} from "../aqlTypes.ts";

export type QBCondition = {
    operator: string;
    rightOperand: AQLValue;
} & Omit<AQLCondition, 'operator' | 'rightOperand'>;
