import {PhraseanetMetaStruct, PhraseanetMetadata} from './types';

export class CPhraseanetMetadata {
    meta_structure_id: string = '';
    name: string = '';
    value: string = '';
    values: string[] = [];
    metaStructure?: PhraseanetMetaStruct;

    static NullMetadata = new CPhraseanetMetadata();

    static fromTPhraseanetMetadata(Tm: PhraseanetMetadata) {
        const m = new CPhraseanetMetadata();
        m.meta_structure_id = Tm.meta_structure_id;
        m.name = Tm.name;
        return m;
    }

    static fromString(s: string) {
        const m = new CPhraseanetMetadata();
        m.values.push((m.value = s));
        return m;
    }
}
