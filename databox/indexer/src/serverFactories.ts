import {AssetServerFactory} from './server';
import {s3AmqpAssetServerFactory} from './handlers/s3_amqp/server';
import {fsAssetServerFactory} from './handlers/fs/server';
import {phraseanetAssetServerFactory} from './handlers/phraseanet/server';

export const assetServerFactories: Record<string, AssetServerFactory<any>> = {
    s3_amqp: s3AmqpAssetServerFactory,
    fs: fsAssetServerFactory,
    phraseanet: phraseanetAssetServerFactory,
};
