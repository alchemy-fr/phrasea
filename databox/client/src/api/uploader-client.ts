import config from '../config';
import {configureClientAuthentication} from '@alchemy/auth';
import {createHttpClient} from '@alchemy/api';
import {oauthClient} from './api-client.ts';

const uploaderClient = createHttpClient(config.uploaderApiBaseUrl);

configureClientAuthentication(uploaderClient, oauthClient);

export default uploaderClient;
