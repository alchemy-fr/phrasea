/**
 * Layout of the `/e2e` volume shared by the two containers of the suite: the
 * e2e container writes the fixtures there, the databox-indexer container reads
 * them and drops its log next to them.
 */

export const E2E_DIR = '/e2e';

/**
 * The indexer is started with `--workdir /e2e`, which is where configLoader
 * looks for `config/${CONFIG_FILE}`.
 */
export const CONFIG_DIR = `${E2E_DIR}/config`;
export const CONFIG_FILE = 'config.e2e.json';

/** `dir` and `sourceDir` of the e2e location. */
export const SRC_DIR = `${E2E_DIR}/src`;

/** Written by the first verification pass, read by the second. */
export const SNAPSHOT = `${E2E_DIR}/snapshot.json`;

export function runLog(run: number): string {
    return `${E2E_DIR}/run${run}.log`;
}
