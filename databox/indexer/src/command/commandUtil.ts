import {setLogLevel} from "../lib/logger";
import {CommandCommonOptions} from "../types";

export function applyCommonOptions<O extends CommandCommonOptions>(opts: O): void {
    if (opts.debug) {
        setLogLevel('debug');
    }
}
