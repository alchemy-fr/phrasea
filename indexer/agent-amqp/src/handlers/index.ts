import {s3AmqpHandler} from "./s3_amqp";
import {fsHandler} from "./fs";
import {IndexLocation} from "../types/config";
import {DataboxClient} from "../lib/databox/client";

type Handler = (location: IndexLocation, databoxClient: DataboxClient) => void;

export const handlers: Record<string, Handler> = {
    s3_amqp: s3AmqpHandler,
    fs: fsHandler,
}
