import {getConfig} from "./configLoader";
import {Logger} from "winston";

const whitelist: string[] | null = getConfig('whitelist', null);
const blacklist: string[] | null = getConfig('blacklist', null);

export function passFilters(path: string, logger: Logger): boolean {
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

