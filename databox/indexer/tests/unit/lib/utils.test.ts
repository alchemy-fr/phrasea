import {forceArray} from '../../../src/lib/utils';

describe('forceArray', () => {
    it('returns arrays untouched', () => {
        const input = [1, 2, 3];

        expect(forceArray(input)).toBe(input);
        expect(forceArray([])).toEqual([]);
    });

    it('turns an object into its values', () => {
        expect(forceArray({a: 1, b: 2})).toEqual([1, 2]);
        expect(forceArray({})).toEqual([]);
    });

    it('passes scalars through', () => {
        expect(forceArray(undefined as any)).toBeUndefined();
        expect(forceArray('str' as any)).toEqual('str');
        expect(forceArray(42 as any)).toEqual(42);
    });

    it('passes null through, as the signature advertises', () => {
        // `typeof null === 'object'` used to send null down the Object.keys()
        // branch, which threw.
        expect(forceArray(null as any)).toBeNull();
    });
});
