import {
    castToBoolean,
    castToInt,
    config,
    getConfig,
} from '../../src/configLoader';

describe('castToBoolean', () => {
    it.each([
        [true, true],
        [false, false],
        ['true', true],
        ['1', true],
        ['y', true],
        ['false', false],
        ['0', false],
        ['no', false],
        ['', false],
        [null, false],
        [undefined, false],
    ])('casts %p to %p', (input, expected) => {
        expect(castToBoolean(input as any)).toEqual(expected);
    });
});

describe('castToInt', () => {
    it.each([
        [3, 3],
        [0, 0],
        ['42', 42],
        ['-7', -7],
        ['12abc', 12],
        ['abc', undefined],
        ['', undefined],
        [null, undefined],
        [undefined, undefined],
    ])('casts %p to %p', (input, expected) => {
        expect(castToInt(input as any)).toEqual(expected);
    });
});

describe('getConfig', () => {
    it('resolves a dotted path', () => {
        expect(getConfig('databox.clientId')).toEqual('test-client');
    });

    it('resolves a top-level key', () => {
        expect(Array.isArray(getConfig('locations'))).toBe(true);
    });

    it('returns the default for a missing path', () => {
        expect(getConfig('databox.nope')).toBeUndefined();
        expect(getConfig('databox.nope', 'fallback')).toEqual('fallback');
        expect(getConfig('nothing.here.at.all', 42)).toEqual(42);
    });

    it('returns the default when the value is explicitly undefined', () => {
        const root = {a: undefined};

        expect(getConfig('a', 'fallback', root)).toEqual('fallback');
    });

    it('does not fall back for other falsy values', () => {
        const root = {zero: 0, empty: '', no: false};

        expect(getConfig('zero', 'fallback', root)).toEqual(0);
        expect(getConfig('empty', 'fallback', root)).toEqual('');
        expect(getConfig('no', 'fallback', root)).toEqual(false);
    });

    it('accepts an explicit root object', () => {
        const root = {a: {b: {c: 'deep'}}};

        expect(getConfig('a.b.c', undefined, root)).toEqual('deep');
    });

    it('ignores inherited properties', () => {
        expect(getConfig('constructor')).toBeUndefined();
        expect(getConfig('toString', 'fallback')).toEqual('fallback');
    });
});

describe('env placeholder substitution', () => {
    const location = (name: string) =>
        config.locations.find(l => l.name === name)!;

    it('replaces %env(VAR)% with the raw value', () => {
        expect(config.databox.url).toEqual('http://databox-api.test');
        expect(location('fs_test').options.dir).toEqual('/fs-watch');
    });

    it('applies the bool: transformer', () => {
        expect(config.databox.verifySSL).toBe(false);
    });

    it('applies the int: transformer', () => {
        expect(config.databox.concurrency).toEqual(2);
    });

    it('recurses into nested objects and arrays', () => {
        expect(location('unset_test').options.nested.list).toEqual([
            'fs',
            'literal',
            42,
            true,
        ]);
    });

    it('leaves non-string values alone', () => {
        expect(location('fs_test').options.createNewWorkspace).toBe(true);
        expect(config.blacklist).toEqual(['(^|/)\\..+$']);
    });

    it('yields undefined when the referenced variable is unset', () => {
        expect(location('unset_test').options.emptyEnv).toBeUndefined();
    });

    it('preserves a "0" env value through the int:/bool: transformers', () => {
        // '0' is a truthy JS string, so it survives replaceEnv's `v || ''`.
        expect(process.env.ZERO_INT).toEqual('0');
        expect(location('unset_test').options.zeroInt).toEqual(0);

        expect(process.env.FALSY_BOOL).toEqual('0');
        expect(location('unset_test').options.falsyBool).toBe(false);
    });
});
