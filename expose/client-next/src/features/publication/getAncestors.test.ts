import {describe, expect, it} from 'vitest';
import type {Publication} from '@/types/api';
import {getAncestors} from './PublicationHeader';

const base = {id: 'c', authorized: true} as Publication;

describe('getAncestors', () => {
    it('has none for a root publication', () => {
        expect(getAncestors(base)).toEqual({gap: false});
    });

    it('has the parent when it is the root', () => {
        const parent = {id: 'r'};
        expect(
            getAncestors({...base, parent, rootPublication: parent})
        ).toEqual({parent, gap: false});
    });

    it('has the root and the parent, with a gap between them', () => {
        const root = {id: 'r'};
        expect(
            getAncestors({
                ...base,
                parent: {id: 'p', parentId: 'x'},
                rootPublication: root,
            })
        ).toMatchObject({root, gap: true});
        expect(
            getAncestors({
                ...base,
                parent: {id: 'p', parentId: 'r'},
                rootPublication: root,
            })
        ).toMatchObject({root, gap: false});
    });
});
