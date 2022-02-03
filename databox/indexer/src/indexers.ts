import {IndexLocation} from "./types/config";
import {DataboxClient} from "./databox/client";
import {Logger} from "winston";
import {s3AmqpIndexer} from "./handlers/s3_amqp/indexer";
import {fsIndexer} from "./handlers/fs/indexer";

type OnProgress = (i: number, total: number | undefined) => void;

export type Indexer<T extends Record<string, any> = any> = (
    location: IndexLocation<T>,
    databoxClient: DataboxClient,
    logger: Logger,
    onProgress: OnProgress
) => Promise<void>;

export const indexers: Record<string, Indexer> = {
    s3_amqp: s3AmqpIndexer,
    fs: fsIndexer,
}
