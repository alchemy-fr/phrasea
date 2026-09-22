import {CPhraseanetMetadata} from '../../../../src/handlers/phraseanet/CPhraseanetMetadata';
import {makeMetadata} from '../../../helpers/phraseanet';

describe('fromTPhraseanetMetadata', () => {
    it('carries the name and structure id but not the value', () => {
        const m = CPhraseanetMetadata.fromTPhraseanetMetadata(
            makeMetadata('Title', 'A title', '7')
        );

        expect(m.name).toEqual('Title');
        expect(m.meta_structure_id).toEqual('7');
        // The value is pushed by CPhraseanetRecord, not by the factory.
        expect(m.value).toEqual('');
        expect(m.values).toEqual([]);
    });
});

describe('fromString', () => {
    it('sets both the scalar value and the single-entry list', () => {
        const m = CPhraseanetMetadata.fromString('hello');

        expect(m.value).toEqual('hello');
        expect(m.values).toEqual(['hello']);
    });

    it('returns a fresh instance each time', () => {
        expect(CPhraseanetMetadata.fromString('a')).not.toBe(
            CPhraseanetMetadata.fromString('a')
        );
    });
});

describe('NullMetadata', () => {
    it('is empty by default', () => {
        expect(CPhraseanetMetadata.NullMetadata.value).toEqual('');
        expect(CPhraseanetMetadata.NullMetadata.name).toEqual('');
    });

    it('hands out a fresh instance every time, so a mutation cannot leak', () => {
        // It used to be a static singleton returned for every missing field,
        // so any caller mutating it corrupted the value seen by all the others
        // for the rest of the process.
        const a = CPhraseanetMetadata.NullMetadata;
        const b = CPhraseanetMetadata.NullMetadata;

        expect(a).not.toBe(b);

        a.values.push('leaked');
        a.value = 'leaked';

        expect(b.values).toEqual([]);
        expect(b.value).toEqual('');
        expect(CPhraseanetMetadata.NullMetadata.values).toEqual([]);
    });
});
