import {DataboxClient} from "../client";

export type IndexAsset = (databoxClient: DataboxClient, path: string) => Promise<void>;
