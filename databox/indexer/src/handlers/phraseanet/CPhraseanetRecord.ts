import PhraseanetClient from "./phraseanetClient";
import {
    PhraseanetStatusBit,
    SubDef,
    PhraseanetRecord,
    PhraseanetStory,
} from "./types";
import {CPhraseanetMetadata} from "./CPhraseanetMetadata";

class CPhraseanetRecordBase {
    client:PhraseanetClient = {} as PhraseanetClient;
    resource_id: string = "";
    databox_id: string = "";
    base_id: string = "";
    uuid: string = "";
    title: string = "";
    original_name: string = "";
    subdefs: SubDef[] = [];
    metadata: Record<string, CPhraseanetMetadata> = {};
    status: PhraseanetStatusBit[] = [];

    async getMetadata(fieldName: string, defaultValue?: string): Promise<CPhraseanetMetadata> {
        if(this.metadata[fieldName]) {
            return this.metadata[fieldName];
        }
        if(defaultValue !== undefined) {
            return CPhraseanetMetadata.fromString(defaultValue);
        }

        return CPhraseanetMetadata.NullMetadata;
    }

    constructor(r:PhraseanetRecord|PhraseanetStory, client:PhraseanetClient) {
        this.client = client;
        this.resource_id = r.resource_id;
        this.databox_id = r.databox_id;
        this.base_id = r.base_id;
        this.uuid = r.uuid;
        this.title = r.title;
        this.original_name = r.original_name;
        this.subdefs = r.subdefs;
        this.status = r.status;
        r.metadata.map((m) => {
            if(!this.metadata[m.name]) {
                this.metadata[m.name] = CPhraseanetMetadata.fromTPhraseanetMetadata(m);
            }
            this.metadata[m.name].values.push(m.value);
        })
        for(const k in this.metadata) {
            this.metadata[k].value = this.metadata[k].values.join(' ; ')
        }
    }
}

export class CPhraseanetRecord extends CPhraseanetRecordBase{
    record_id: string = "";
    constructor(r: PhraseanetRecord, client:PhraseanetClient) {
        super(r, client);
        this.record_id = r.record_id;
    }
}

export class CPhraseanetStory extends CPhraseanetRecordBase {
    story_id: string = "";
    children: CPhraseanetRecord[] = [];
    constructor(s: PhraseanetStory, client:PhraseanetClient) {
        super(s, client);
        this.story_id = s.story_id;
    }
}
