import PhraseanetClient from './phraseanetClient';
import {
    PhraseanetStatusBit,
    PhraseanetSubdef,
    PhraseanetRecord,
    PhraseanetStory,
} from './types';
import {CPhraseanetMetadata} from './CPhraseanetMetadata';
import {CPhraseanetSubdef} from './CPhraseanetSubdef';

class CPhraseanetRecordBase {
    client: PhraseanetClient = {} as PhraseanetClient;
    resource_id: string = '';
    databox_id: string = '';
    base_id: string = '';
    uuid: string = '';
    title: string = '';
    original_name: string = '';
    mime_type: string = '';
    created_on: string = '';
    updated_on: string = '';
    subdefs: PhraseanetSubdef[] = [];
    metadata: Record<string, CPhraseanetMetadata> = {};
    status: PhraseanetStatusBit[] = [];
    private csubdefs: Record<string, CPhraseanetSubdef> = {};

    async getMetadata(
        fieldName: string,
        defaultValue?: string
    ): Promise<CPhraseanetMetadata> {
        if (this.metadata[fieldName]) {
            return this.metadata[fieldName];
        }
        if (defaultValue !== undefined) {
            return CPhraseanetMetadata.fromString(defaultValue);
        }

        return CPhraseanetMetadata.NullMetadata;
    }

    async getStatus(
        bit: number,
        valueTrue?: string,
        valueFalse?: string
    ): Promise<string | boolean> {
        const vTrue: string | boolean = valueTrue ?? true;
        const vFalse: string | boolean = valueFalse ?? false;
        for (const s of this.status) {
            if (s.bit === bit) {
                return s.state ? vTrue : vFalse;
            }
        }
        return vFalse;
    }

    async getSubdef(name: string): Promise<CPhraseanetSubdef> {
        return this.csubdefs[name] ?? CPhraseanetSubdef.NullSubdef;
    }

    constructor(
        r: PhraseanetRecord | PhraseanetStory,
        client: PhraseanetClient
    ) {
        this.client = client;
        this.resource_id = r.resource_id;
        this.databox_id = r.databox_id;
        this.base_id = r.base_id;
        this.uuid = r.uuid;
        this.title = r.title;
        this.original_name = r.original_name;
        this.subdefs = r.subdefs;
        this.status = r.status;
        this.mime_type = r.mime_type;
        this.created_on = r.created_on;
        this.updated_on = r.updated_on;
        r.metadata.map(m => {
            if (m.value.trim() !== '') {
                if (!this.metadata[m.name]) {
                    this.metadata[m.name] =
                        CPhraseanetMetadata.fromTPhraseanetMetadata(m);
                }
                this.metadata[m.name].values.push(m.value);
            }
        });

        for (const k in this.metadata) {
            this.metadata[k].values.sort((a, b) => {
                a = a.toLowerCase();
                b = b.toLowerCase();
                return a < b ? -1 : a > b ? 1 : 0;
            });
            this.metadata[k].value = this.metadata[k].values.join(' ; ');
        }

        r.subdefs.map(s => {
            this.csubdefs[s.name] = new CPhraseanetSubdef(s);
        });
    }
}

export class CPhraseanetRecord extends CPhraseanetRecordBase {
    record_id: string = '';
    constructor(r: PhraseanetRecord, client: PhraseanetClient) {
        super(r, client);
        this.record_id = r.record_id;
    }
}

export class CPhraseanetStory extends CPhraseanetRecordBase {
    story_id: string = '';
    children: CPhraseanetRecord[] = [];
    constructor(s: PhraseanetStory, client: PhraseanetClient) {
        super(s, client);
        this.story_id = s.story_id;
    }
}
