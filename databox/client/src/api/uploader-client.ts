import config from "../config";
import {createHttpClient} from "react-ps";

const uploaderClient = createHttpClient(config.uploaderApiBaseUrl);

export default uploaderClient;
