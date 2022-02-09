import {IndexLocation} from "./types/config";
import {Logger} from "winston";
import {s3AmqpIterator} from "./handlers/s3_amqp/indexer";
import {fsIndexer} from "./handlers/fs/indexer";
import {phraseanetIndexer} from "./handlers/phraseanet/indexer";
import {DataboxClient} from "./databox/client";
import {AttributeInput} from "./databox/types";

export type Asset = {
    key: string;
    path: string;
    publicUrl: string;
    sourcePath?: string;
    attributes?: AttributeInput[];
}

export type IndexIterator<T extends Record<string, any> = any> = (
    location: IndexLocation<T>,
    logger: Logger,
    databoxClient: DataboxClient,
) => AsyncGenerator<Asset, void>;

export const indexers: Record<string, IndexIterator> = {
    s3_amqp: s3AmqpIterator,
    fs: fsIndexer,
    phraseanet: phraseanetIndexer,
}
