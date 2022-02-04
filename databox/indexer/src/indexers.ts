import {IndexLocation} from "./types/config";
import {DataboxClient} from "./databox/client";
import {Logger} from "winston";
import {s3AmqpIterator} from "./handlers/s3_amqp/indexer";
import {fsIndexer} from "./handlers/fs/indexer";

export type Asset = {
    publicUrl: string;
    path: string;
}

export type IndexIterator<T extends Record<string, any> = any> = (
    location: IndexLocation<T>,
    logger: Logger,
) => AsyncGenerator<Asset, void>;

export const indexers: Record<string, IndexIterator> = {
    s3_amqp: s3AmqpIterator,
    fs: fsIndexer,
}
