import {describe, expect, it} from 'vitest';
import {parseTaskPath} from './parseTaskPath';

describe('parseTaskPath', () => {
    it('lists the tasks at the root', () => {
        expect(parseTaskPath([])).toEqual({type: 'list'});
        expect(parseTaskPath(undefined)).toEqual({type: 'list'});
    });

    it('shows the catalog on "new"', () => {
        expect(parseTaskPath(['new'])).toEqual({type: 'catalog'});
    });

    it('shows the run form of a task type', () => {
        expect(parseTaskPath(['new', 'index_assets'])).toEqual({
            type: 'run',
            taskType: 'index_assets',
        });
    });

    it('shows the details of a task by its ID', () => {
        const id = '0b6c8e2a-6f43-4a8e-9a43-0f0d1c6e2b11';
        expect(parseTaskPath([id])).toEqual({type: 'details', id});
    });

    it('does not match deeper paths', () => {
        expect(parseTaskPath(['some-id', 'details'])).toEqual({
            type: 'not_found',
        });
        expect(parseTaskPath(['new', 'index_assets', 'x'])).toEqual({
            type: 'not_found',
        });
    });
});
