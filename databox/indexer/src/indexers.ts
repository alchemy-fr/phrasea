import {IndexLocation} from './types/config';
import {Logger} from 'winston';
import {s3AmqpIterator} from './handlers/s3_amqp/indexer';
import {fsIndexer} from './handlers/fs/indexer';
import {phraseanetIndexer} from './handlers/phraseanet/indexer';
import {DataboxClient} from './databox/client';
import {AttributeInput, RenditionInput, TagInput} from './databox/types';
import {IndexOptions} from './command';

export type Asset = {
    workspaceId: string;
    key: string;
    title?: string;
    path: string;
    collectionKeyPrefix?: string;
    publicUrl?: string;
    isPrivate?: boolean;
    generateRenditions?: boolean;
    sourcePath?: string;
    importFile?: boolean;
    attributes?: AttributeInput[];
    tags?: TagInput[];
    renditions?: RenditionInput[];
    shortcutIntoCollections?: {id: string; path: string}[];
    isStory?: boolean;
};

export type IndexIterator<T extends Record<string, any> = any> = (
    location: IndexLocation<T>,
    logger: Logger,
    databoxClient: DataboxClient,
    options: IndexOptions
) => AsyncGenerator<Asset, void>;

export const indexers: Record<string, IndexIterator> = {
    s3_amqp: s3AmqpIterator,
    fs: fsIndexer,
    phraseanet: phraseanetIndexer,
};
