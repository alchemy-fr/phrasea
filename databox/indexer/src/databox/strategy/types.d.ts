import {DataboxClient} from "../client";
import {Logger} from "winston";

export type IndexAsset = (
    publicUrl: string,
    databoxClient: DataboxClient,
    path: string,
    logger: Logger
) => Promise<void>;
