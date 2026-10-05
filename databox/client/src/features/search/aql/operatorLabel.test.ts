import {describe, expect, it} from 'vitest';
import {getOperatorLabel} from './operatorLabel';
import {AQLOperator, RawType} from './types';

const t = ((_key: string, defaultValue: string) =>
    defaultValue) as unknown as Parameters<typeof getOperatorLabel>[0];

describe('getOperatorLabel', () => {
    it('uses words for text fields', () => {
        expect(getOperatorLabel(t, AQLOperator.EQ, RawType.String)).toBe('Is');
        expect(getOperatorLabel(t, AQLOperator.IN, RawType.String)).toBe(
            'Is any of'
        );
        expect(getOperatorLabel(t, AQLOperator.MISSING, RawType.String)).toBe(
            'Is empty'
        );
    });

    it('uses symbols for numbers', () => {
        expect(getOperatorLabel(t, AQLOperator.EQ, RawType.Number)).toBe('=');
        expect(getOperatorLabel(t, AQLOperator.NEQ, RawType.Number)).toBe('≠');
        expect(getOperatorLabel(t, AQLOperator.LTE, RawType.Number)).toBe('≤');
    });

    it('uses dates vocabulary', () => {
        expect(getOperatorLabel(t, AQLOperator.LT, RawType.Date)).toBe(
            'Is before'
        );
        expect(getOperatorLabel(t, AQLOperator.GTE, RawType.DateTime)).toBe(
            'Is on or after'
        );
        expect(getOperatorLabel(t, AQLOperator.BETWEEN, RawType.Date)).toBe(
            'Is within'
        );
    });
});
