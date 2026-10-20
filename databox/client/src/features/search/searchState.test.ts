import {describe, expect, it} from 'vitest';
import {
    conditionQuery,
    paramsToSearchState,
    searchChecksum,
    searchStateToParams,
    similarSearchState,
} from './searchState';

describe('inverted conditions', () => {
    const condition = {id: 'c', query: 'a = 1 OR b = 2'};

    it('negates the whole query', () => {
        expect(conditionQuery(condition)).toBe('a = 1 OR b = 2');
        expect(conditionQuery({...condition, inversed: true})).toBe(
            'NOT (a = 1 OR b = 2)'
        );
    });

    it('changes the search checksum', () => {
        const state = {query: '', sortBy: [], conditions: [condition]};
        expect(
            searchChecksum({
                ...state,
                conditions: [{...condition, inversed: true}],
            })
        ).not.toBe(searchChecksum(state));
    });
});

describe('similarity search', () => {
    it('searches the similar assets alone, by score', () => {
        const state = similarSearchState('a"b');
        expect(state).toEqual({
            query: '',
            conditions: [{id: '@similar', query: '@similar = "a\\"b"'}],
            sortBy: [],
        });
        expect(paramsToSearchState(searchStateToParams(state))).toMatchObject(
            state
        );
    });
});
