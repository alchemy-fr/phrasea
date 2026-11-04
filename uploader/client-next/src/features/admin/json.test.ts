import {describe, expect, it} from 'vitest';
import {parseJsonObject, toJson} from './json';

describe('parseJsonObject', () => {
    it('parses an object', () => {
        expect(parseJsonObject('{"a": 1}')).toEqual({value: {a: 1}});
    });

    it('rejects invalid JSON and other values', () => {
        expect(parseJsonObject('{a').error).toBeTruthy();
        expect(parseJsonObject('[]').error).toBeTruthy();
        expect(parseJsonObject('null').error).toBeTruthy();
    });

    it('round-trips with toJson', () => {
        expect(parseJsonObject(toJson({a: [1]})).value).toEqual({a: [1]});
    });
});
