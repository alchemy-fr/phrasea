import {describe, expect, it} from 'vitest';
import type {Asset} from '@/types/api';
import {resolvePayload} from './AppDndProvider';

const asset = (id: string, deleted = false) =>
    ({id, deleted}) as unknown as Asset;
const assetsOf = (payload: ReturnType<typeof resolvePayload>) =>
    payload?.type === 'assets' ? payload.assets : undefined;

describe('resolvePayload', () => {
    const source = (asset_: Asset, selection: Asset[]) => ({
        kind: 'asset-source' as const,
        asset: asset_,
        scope: 'list',
        basketId: 'b',
        getSelection: () => selection,
    });

    it('takes the selection along when the dragged card is selected', () => {
        const a = asset('a');
        const b = asset('b');
        expect(resolvePayload(source(a, [a, b]))).toEqual({
            type: 'assets',
            assets: [a, b],
            source: {scope: 'list', basketId: 'b'},
        });
    });

    it('drags the card alone when it is not selected', () => {
        const a = asset('a');
        expect(assetsOf(resolvePayload(source(a, [asset('b')])))).toEqual([a]);
    });

    it('leaves deleted assets out', () => {
        const a = asset('a');
        const gone = asset('gone', true);
        expect(assetsOf(resolvePayload(source(a, [a, gone])))).toEqual([a]);
        expect(resolvePayload(source(gone, []))).toBeNull();
    });
});
