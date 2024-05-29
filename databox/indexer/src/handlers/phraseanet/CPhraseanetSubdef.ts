import {PhraseanetSubdef} from "./types";

export class CPhraseanetSubdef {
    name?: string;
    height?: number;
    width?: number;
    filesize?: number;
    player_type?: string;
    mime_type?: string;
    created_on?: string;
    updated_on?: string;
    url?: string;
    permalink?: {
        url?: string;
    };

    static NullSubdef = new CPhraseanetSubdef();

    constructor(s?: PhraseanetSubdef) {
        if(s) {
            this.name = s.name;
            this.height = s.height;
            this.width = s.width;
            this.filesize = s.filesize;
            this.player_type = s.player_type;
            this.mime_type = s.mime_type;
            this.created_on = s.created_on;
            this.updated_on = s.updated_on;
            this.url = s.url;
            this.permalink = s.permalink;
        }
    }

}

