import {IndexLocation} from './types/config';
import {DataboxClient} from './databox/client';
import {Logger} from 'winston';
import {s3AmqpWatcher} from './handlers/s3_amqp/watcher';
import {fsWatcher} from './handlers/fs/watcher';
import {phraseanetWatcher} from './handlers/phraseanet/watcher';

export type Watcher<T extends Record<string, any> = any> = (
    location: IndexLocation<T>,
    databoxClient: DataboxClient,
    logger: Logger
) => void;

export const watchers: Record<string, Watcher> = {
    s3_amqp: s3AmqpWatcher,
    fs: fsWatcher,
    phraseanet: phraseanetWatcher,
};
