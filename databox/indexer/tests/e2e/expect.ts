import {readFileSync} from 'fs';
import {expect} from 'vitest';

/** Compares two lists as sets; vitest prints a readable diff on failure. */
export function expectSet(actual: string[], expected: string[]): void {
    expect([...actual].sort()).toEqual([...expected].sort());
}

/**
 * The indexer log of a run.
 *
 * The `http` logger legitimately reports the 404 of the
 * GET /workspaces-by-slug probe initWorkspace uses to decide whether to create
 * the workspace, so that context is excluded from the error scan.
 */
export function readRunLog(path: string): string {
    return readFileSync(path, 'utf8');
}

export function expectNoLoggedError(log: string): void {
    const errors = log
        .split('\n')
        .filter(line =>
            /\.ERROR:|Uncaught exception|Missing (env|config)/.test(line)
        )
        .filter(line => !/http\.ERROR:/.test(line));

    expect(errors, 'errors logged by the indexer').toEqual([]);
}
