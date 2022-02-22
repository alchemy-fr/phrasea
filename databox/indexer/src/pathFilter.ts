import {getConfig} from "./configLoader";
import {Logger} from "winston";
import {Asset} from "./indexers";

const whitelist: string[] | null = getConfig('whitelist', null);
const blacklist: string[] | null = getConfig('blacklist', null);

export function passFilters(asset: Asset, logger: Logger): boolean {
    const {path} = asset;
    if (whitelist && !whitelist.some(w => path.match(new RegExp(w)))) {
        logger.debug(`"${path}" does not match whitelist, skipping...`);
        return false;
    }
    if (blacklist && blacklist.some(w => path.match(new RegExp(w)))) {
        logger.debug(`"${path}" does not match blacklist, skipping...`);
        return false;
    }

    return true;
}

