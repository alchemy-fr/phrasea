import config from "../config";
import {createHttpClient} from "react-ps";

const uploaderClient = createHttpClient(config.get('uploaderApiBaseUrl'));

export default uploaderClient;
