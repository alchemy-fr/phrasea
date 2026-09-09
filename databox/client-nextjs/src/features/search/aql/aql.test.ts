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
        expect(roundTrip('@deleted = true')).toBe('@deleted = true');
        expect(roundTrip('flag != false')).toBe('flag != false');
        expect(roundTrip('value = null')).toBe('value = null');
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
        expect(roundTrip('(a = 1 OR b = 2) AND c = 3')).toBe(
            '(a = 1 OR b = 2) AND c = 3'
        );
        expect(roundTrip('NOT a = 1')).toBe('NOT a = 1');
    });

    it('parses IN, NOT IN, BETWEEN, MISSING, EXISTS', () => {
        expect(roundTrip('tag IN ("a", "b")')).toBe('tag IN ("a", "b")');
        expect(roundTrip('tag NOT IN ("a")')).toBe('tag NOT IN ("a")');
        expect(roundTrip('@createdAt BETWEEN "2020" AND "2021"')).toBe(
            '@createdAt BETWEEN "2020" AND "2021"'
        );
        expect(roundTrip('x NOT BETWEEN 1 AND 2')).toBe(
            'x NOT BETWEEN 1 AND 2'
        );
        expect(roundTrip('x IS MISSING')).toBe('x IS MISSING');
        expect(roundTrip('x EXISTS')).toBe('x EXISTS');
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
        expect(roundTrip('x = (1 + 2) * 3')).toBe('x = (1 + 2) * 3');
        expect(roundTrip('x = DATE("2020-01-01", 1)')).toBe(
            'x = DATE("2020-01-01", 1)'
        );
    });

    it('handles escaped quotes and single quotes', () => {
        expect(parseAQL('t = "a \\"b\\" c"', true)!.expression).toMatchObject({
            rightOperand: {literal: 'a "b" c'},
        });
        expect(roundTrip("t = 'abc'")).toBe('t = "abc"');
    });

    it('parses entity references', () => {
        const ast = parseAQL('tag = @<123:My tag>', true)!;
        expect(ast.expression).toMatchObject({
            rightOperand: {type: 'entity', id: '123', label: 'My tag'},
        });
        expect(astToString(ast)).toBe('tag = @<123:My tag>');
    });

    it('rejects invalid input', () => {
        expect(() => parseAQL('title =', true)).toThrow(AQLSyntaxError);
        expect(() => parseAQL('= 1', true)).toThrow(AQLSyntaxError);
        expect(() => parseAQL('a = "unterminated', true)).toThrow(
            AQLSyntaxError
        );
        expect(parseAQL('a = ')).toBeUndefined();
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
            'tag = "b" OR tag IS MISSING'
        );
    });

    it('serializes numbers and booleans', () => {
        expect(new ConditionBuilder('@privacy', [1, 2]).toString()).toBe(
            '@privacy IN (1, 2)'
        );
        expect(new ConditionBuilder('flag', [true]).toString()).toBe(
            'flag = true'
        );
    });
});
