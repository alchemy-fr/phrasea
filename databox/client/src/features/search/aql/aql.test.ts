import {describe, expect, it} from 'vitest';
import {parseAQL, AQLSyntaxError} from './parser';
import {astToString, ConditionBuilder} from './serializer';
import {AQLLogical, AQLOperator} from './types';

function roundTrip(query: string): string {
    return astToString(parseAQL(query, true));
}

describe('AQL parser', () => {
    it('parses a simple equality', () => {
        const ast = parseAQL('title = "Hello"', true)!;
        expect(ast.expression).toEqual({
            leftOperand: {field: 'title'},
            operator: AQLOperator.EQ,
            rightOperand: {literal: 'Hello'},
        });
    });

    it('parses built-in fields, booleans, null and numbers', () => {
        expect(roundTrip('@deleted IS true')).toBe('@deleted IS true');
        expect(roundTrip('flag IS NOT false')).toBe('flag IS NOT false');
        expect(roundTrip('value IS null')).toBe('value IS null');
        expect(roundTrip('@size > 1024')).toBe('@size > 1024');
        expect(roundTrip('price <= 10.5')).toBe('price <= 10.5');
    });

    it('parses AND / OR / NOT with precedence', () => {
        const ast = parseAQL('a = 1 OR b = 2 AND c = 3', true)!;
        expect(ast.expression).toMatchObject({
            operator: AQLLogical.OR,
            conditions: [
                {operator: AQLOperator.EQ},
                {operator: AQLLogical.AND},
            ],
        });
        expect(roundTrip('(a IS 1 OR b IS 2) AND c IS 3')).toBe(
            '(a IS 1 OR b IS 2) AND c IS 3'
        );
        expect(roundTrip('NOT a IS 1')).toBe('NOT a IS 1');
    });

    it('parses IS ANY OF, IS NONE OF, HAS ALL OF, BETWEEN, IS [NOT] EMPTY', () => {
        expect(roundTrip('tag IS ANY OF ("a", "b")')).toBe(
            'tag IS ANY OF ("a", "b")'
        );
        expect(roundTrip('tag IS NONE OF ("a")')).toBe('tag IS NONE OF ("a")');
        expect(roundTrip('tag HAS ALL OF ("a", "b")')).toBe(
            'tag HAS ALL OF ("a", "b")'
        );
        expect(roundTrip('@createdAt BETWEEN "2020" AND "2021"')).toBe(
            '@createdAt BETWEEN "2020" AND "2021"'
        );
        expect(roundTrip('x NOT BETWEEN 1 AND 2')).toBe(
            'x NOT BETWEEN 1 AND 2'
        );
        expect(roundTrip('x IS EMPTY')).toBe('x IS EMPTY');
        expect(roundTrip('x IS NOT EMPTY')).toBe('x IS NOT EMPTY');
    });

    it('parses legacy operators as aliases', () => {
        expect(roundTrip('t = "a"')).toBe('t IS "a"');
        expect(roundTrip('t != "a"')).toBe('t IS NOT "a"');
        expect(roundTrip('tag IN ("a", "b")')).toBe('tag IS ANY OF ("a", "b")');
        expect(roundTrip('tag HAS ANY OF ("a")')).toBe('tag IS ANY OF ("a")');
        expect(roundTrip('tag NOT IN ("a")')).toBe('tag IS NONE OF ("a")');
        expect(roundTrip('tag HAS NONE OF ("a")')).toBe('tag IS NONE OF ("a")');
        expect(roundTrip('x IS MISSING')).toBe('x IS EMPTY');
        expect(roundTrip('x EXISTS')).toBe('x IS NOT EMPTY');
        expect(roundTrip('t is not other')).toBe('t IS NOT other');
    });

    it('parses text operators', () => {
        expect(roundTrip('t CONTAINS "a"')).toBe('t CONTAINS "a"');
        expect(roundTrip('t DOES NOT CONTAIN "a"')).toBe(
            't DOES NOT CONTAIN "a"'
        );
        expect(roundTrip('t MATCHES "a"')).toBe('t MATCHES "a"');
        expect(roundTrip('t DOES NOT MATCH "a"')).toBe('t DOES NOT MATCH "a"');
        expect(roundTrip('t STARTS WITH "a"')).toBe('t STARTS WITH "a"');
        expect(roundTrip('t DOES NOT START WITH "a"')).toBe(
            't DOES NOT START WITH "a"'
        );
        expect(roundTrip('t ENDS WITH "a"')).toBe('t ENDS WITH "a"');
        expect(roundTrip('t DOES NOT END WITH "a"')).toBe(
            't DOES NOT END WITH "a"'
        );
    });

    it('parses geo operators', () => {
        expect(roundTrip('loc WITHIN CIRCLE(48.8, 2.3, 1000)')).toBe(
            'loc WITHIN CIRCLE (48.8, 2.3, 1000)'
        );
        expect(roundTrip('loc WITHIN RECTANGLE(1, 2, 3, 4)')).toBe(
            'loc WITHIN RECTANGLE (1, 2, 3, 4)'
        );
    });

    it('parses functions and arithmetic', () => {
        expect(roundTrip('@createdAt > NOW() - 86400')).toBe(
            '@createdAt > NOW() - 86400'
        );
        expect(roundTrip('x IS (1 + 2) * 3')).toBe('x IS (1 + 2) * 3');
        expect(roundTrip('x IS DATE("2020-01-01", 1)')).toBe(
            'x IS DATE("2020-01-01", 1)'
        );
    });

    it('handles escaped quotes and single quotes', () => {
        expect(parseAQL('t = "a \\"b\\" c"', true)!.expression).toMatchObject({
            rightOperand: {literal: 'a "b" c'},
        });
        expect(roundTrip("t = 'abc'")).toBe('t IS "abc"');
    });

    it('parses entity references', () => {
        const ast = parseAQL('tag = @<123:My tag>', true)!;
        expect(ast.expression).toMatchObject({
            rightOperand: {type: 'entity', id: '123', label: 'My tag'},
        });
        expect(astToString(ast)).toBe('tag IS @<123:My tag>');
    });

    it('rejects invalid input', () => {
        expect(() => parseAQL('title =', true)).toThrow(AQLSyntaxError);
        expect(() => parseAQL('= 1', true)).toThrow(AQLSyntaxError);
        expect(() => parseAQL('a = "unterminated', true)).toThrow(
            AQLSyntaxError
        );
        expect(parseAQL('a = ')).toBeUndefined();
        expect(() => parseAQL('a IS ANY OF "x"', true)).toThrow(AQLSyntaxError);
        expect(() => parseAQL('a HAS ALL OF', true)).toThrow(AQLSyntaxError);
        expect(() => parseAQL('a IS', true)).toThrow(AQLSyntaxError);
    });
});

describe('ConditionBuilder', () => {
    it('reads values back from a query', () => {
        const b = ConditionBuilder.fromQuery(
            'tag',
            parseAQL('tag IN ("a", "b") OR tag IS MISSING')
        );
        expect(b.getValues()).toEqual(['a', 'b']);
        expect(b.includeMissing).toBe(true);
        expect(b.toggleValue('a').toString()).toBe(
            'tag IS "b" OR tag IS EMPTY'
        );
    });

    it('serializes numbers and booleans', () => {
        expect(new ConditionBuilder('@privacy', [1, 2]).toString()).toBe(
            '@privacy IS ANY OF (1, 2)'
        );
        expect(new ConditionBuilder('flag', [true]).toString()).toBe(
            'flag IS true'
        );
    });
});
