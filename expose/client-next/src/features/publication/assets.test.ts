import {describe, expect, it} from 'vitest';
import type {Asset} from '@/types/api';
import {cycle, findAsset} from './assets';

const assets = [
    {id: 'a', mimeType: 'image/jpeg', slug: 'first'},
    {id: 'b', mimeType: 'image/jpeg'},
    {id: 'c', mimeType: 'image/jpeg'},
] as Asset[];

describe('findAsset', () => {
    it('finds an asset by id or slug', () => {
        expect(findAsset(assets, 'b')?.id).toBe('b');
        expect(findAsset(assets, 'first')?.id).toBe('a');
        expect(findAsset(assets, 'nope')).toBeUndefined();
        expect(findAsset(assets, undefined)).toBeUndefined();
    });
});

describe('cycle', () => {
    it('goes around', () => {
        expect(cycle(assets, assets[0], 1).id).toBe('b');
        expect(cycle(assets, assets[2], 1).id).toBe('a');
        expect(cycle(assets, assets[0], -1).id).toBe('c');
        expect(cycle([assets[0]], assets[0], 1).id).toBe('a');
    });
});
