import {PhraseanetMetaStruct, PhraseanetMetadata} from './types';

export class CPhraseanetMetadata {
    meta_structure_id: string = '';
    name: string = '';
    value: string = '';
    values: string[] = [];
    metaStructure?: PhraseanetMetaStruct;

    /**
     * A fresh, empty metadata. A shared instance would be handed out for every
     * missing field, and one caller mutating it would corrupt the value every
     * other caller sees for the rest of the process.
     */
    static get NullMetadata(): CPhraseanetMetadata {
        return new CPhraseanetMetadata();
    }

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
