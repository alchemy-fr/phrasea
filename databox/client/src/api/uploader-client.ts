import config from '../config';
import {createHttpClient} from '@alchemy/auth';

const uploaderClient = createHttpClient(config.uploaderApiBaseUrl);

export default uploaderClient;
