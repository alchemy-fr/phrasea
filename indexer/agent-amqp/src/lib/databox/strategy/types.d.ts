import {DataboxClient} from "../client";

export type IndexAsset = (publicUrl: string, databoxClient: DataboxClient, path: string) => Promise<void>;
