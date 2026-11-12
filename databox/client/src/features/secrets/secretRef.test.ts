import {describe, expect, it} from 'vitest';
import {
    configRefersTo,
    isValidSecretName,
    parseReference,
    refersTo,
    suggestSecretName,
    toReference,
} from './secretRef';

describe('secretRef', () => {
    it('reads a whole reference only', () => {
        expect(parseReference('${API_KEY}')).toBe('API_KEY');
        expect(parseReference('$API_KEY')).toBe('API_KEY');
        expect(parseReference('https://${HOST}/x')).toBeUndefined();
        expect(parseReference('plain')).toBeUndefined();
        expect(parseReference('')).toBeUndefined();
        expect(parseReference(42)).toBeUndefined();
        expect(toReference('API_KEY')).toBe('${API_KEY}');
    });

    it('accepts the names both reference forms resolve', () => {
        expect(isValidSecretName('API_KEY')).toBe(true);
        expect(isValidSecretName('_key2')).toBe(true);
        expect(isValidSecretName('foo-bar')).toBe(false);
        expect(isValidSecretName('1ABC')).toBe(false);
        expect(isValidSecretName('A'.repeat(101))).toBe(false);
    });

    it('suggests a name from a configuration key', () => {
        expect(suggestSecretName('apiKey')).toBe('API_KEY');
        expect(suggestSecretName('client.secret')).toBe('CLIENT_SECRET');
        expect(suggestSecretName('2fa')).toBe('_2FA');
        expect(suggestSecretName('')).toBe('SECRET');
    });

    it('finds the references to a name, at word boundary', () => {
        expect(refersTo('${KEY}', 'KEY')).toBe(true);
        expect(refersTo('https://$KEY/x', 'KEY')).toBe(true);
        expect(refersTo('$KEY_2', 'KEY')).toBe(false);
        expect(refersTo('${KEY_2}', 'KEY')).toBe(false);
        expect(
            configRefersTo({a: {b: ['x', 'Bearer ${TOKEN}']}}, 'TOKEN')
        ).toBe(true);
        expect(configRefersTo({a: 1, b: null}, 'TOKEN')).toBe(false);
        expect(configRefersTo(undefined, 'TOKEN')).toBe(false);
    });
});
