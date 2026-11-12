import {describe, expect, it} from 'vitest';
import type {Asset} from '@/types/api';
import {appendPage} from './resultPages';

const asset = (id: string) => ({id}) as Asset;
const ids = (pages: Asset[][]) => pages.map(p => p.map(a => a.id));

describe('appendPage', () => {
    it('appends the next page', () => {
        expect(
            ids(appendPage([[asset('a'), asset('b')]], [asset('c')]))
        ).toEqual([['a', 'b'], ['c']]);
    });

    it('drops the assets already listed by a previous page', () => {
        const pages = [[asset('a'), asset('b')], [asset('c')]];

        expect(
            ids(appendPage(pages, [asset('c'), asset('b'), asset('d')]))
        ).toEqual([['a', 'b'], ['c'], ['d']]);
    });

    it('keeps an empty page when every asset was already listed', () => {
        expect(ids(appendPage([[asset('a')]], [asset('a')]))).toEqual([
            ['a'],
            [],
        ]);
    });
});
