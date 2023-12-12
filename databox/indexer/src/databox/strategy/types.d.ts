import {DataboxClient} from '../client';
import {Logger} from 'winston';
import {Asset} from '../../indexers';
import {IndexLocation} from '../../types/config';

export type IndexAsset = (
    asset: Asset,
    location: IndexLocation<any>,
    databoxClient: DataboxClient,
    logger: Logger
) => Promise<void>;
