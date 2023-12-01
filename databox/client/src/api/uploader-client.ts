import config from '../config';
import {configureClientAuthentication, createHttpClient} from '@alchemy/auth';
import {oauthClient} from './api-client.ts';

const uploaderClient = createHttpClient(config.uploaderApiBaseUrl);

configureClientAuthentication(uploaderClient, oauthClient);

export default uploaderClient;
