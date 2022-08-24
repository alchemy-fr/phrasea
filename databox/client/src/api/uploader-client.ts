import {createHttpClient} from "./http-client";
import config from "../config";

const uploaderClient = createHttpClient(config.get('uploaderApiBaseUrl'));

export default uploaderClient;
