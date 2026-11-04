import {describe, expect, it} from 'vitest';
import {fitValues} from './ConditionBuilder';
import {AQLOperator} from '../aql/types';
import {AttributeType, type AttributeDefinitionOrBuiltIn} from '@/types/api';

const def = (type: AttributeType) =>
    ({type}) as unknown as AttributeDefinitionOrBuiltIn;

describe('fitValues', () => {
    it('defaults a boolean operand to true', () => {
        expect(fitValues([], AQLOperator.EQ, def(AttributeType.Boolean))).toBe(
            true
        );
    });

    it('defaults other operands to an empty literal', () => {
        expect(fitValues([], AQLOperator.EQ, def(AttributeType.Text))).toEqual({
            literal: '',
        });
    });

    it('keeps the existing values and fills the missing ones', () => {
        expect(
            fitValues(
                [{literal: '1'}],
                AQLOperator.BETWEEN,
                def(AttributeType.Number)
            )
        ).toEqual([{literal: '1'}, {literal: ''}]);
    });

    it('drops the operand of a unary operator', () => {
        expect(
            fitValues(
                [{literal: 'x'}],
                AQLOperator.MISSING,
                def(AttributeType.Text)
            )
        ).toBeUndefined();
    });
});
