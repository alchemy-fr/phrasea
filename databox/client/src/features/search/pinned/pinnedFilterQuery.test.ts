import {describe, expect, it} from 'vitest';
import {parseAQL} from '../aql/parser';
import {
    conditionsOn,
    dateRangeOf,
    dateRangeQuery,
    pinnedConditionId,
} from './pinnedFilterQuery';

describe('pinned filter queries', () => {
    it('identifies the condition by its field', () => {
        expect(pinnedConditionId('@createdAt')).toBe('pin-@createdAt');
    });

    it('builds a date range, either bound being optional', () => {
        const range = {from: '2026-10-01', to: '2026-10-12'};
        expect(dateRangeQuery('shot', range, false)).toBe(
            'shot BETWEEN "2026-10-01" AND "2026-10-12"'
        );
        expect(dateRangeQuery('shot', {from: range.from, to: ''}, false)).toBe(
            'shot >= "2026-10-01"'
        );
        expect(dateRangeQuery('shot', {from: '', to: range.to}, false)).toBe(
            'shot <= "2026-10-12"'
        );
        expect(dateRangeQuery('shot', {from: '', to: ''}, false)).toBe(
            undefined
        );
    });

    it('covers whole local days for date-times', () => {
        const query = dateRangeQuery(
            '@createdAt',
            {from: '2026-10-01', to: '2026-10-12'},
            true
        );
        const from = new Date('2026-10-01T00:00:00.000').toISOString();
        const to = new Date('2026-10-12T23:59:59.999').toISOString();
        expect(query).toBe(`@createdAt BETWEEN "${from}" AND "${to}"`);
    });

    it.each([
        [{from: '2026-10-01', to: '2026-10-12'}, false],
        [{from: '2026-10-01', to: ''}, false],
        [{from: '', to: '2026-10-12'}, false],
        [{from: '2026-10-01', to: '2026-10-12'}, true],
        [{from: '', to: '2026-10-12'}, true],
    ])('reads back the range %o (with time: %s)', (range, withTime) => {
        const query = dateRangeQuery('d', range, withTime)!;
        expect(dateRangeOf('d', parseAQL(query), withTime)).toEqual(range);
    });

    it('ignores the conditions on other fields', () => {
        const ast = parseAQL('a = "x" AND b = "y"');
        expect(conditionsOn('b', ast)).toHaveLength(1);
        expect(dateRangeOf('c', ast, false)).toEqual({from: '', to: ''});
    });
});
