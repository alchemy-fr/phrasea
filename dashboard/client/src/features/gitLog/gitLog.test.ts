// @vitest-environment node
import {describe, expect, it} from 'vitest';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import {parseGitLog, readGitLog} from './gitLog';

describe('git log', () => {
    it('parses the lines written by bin/git-log.sh', () => {
        expect(
            parseGitLog(
                'abc1234\t2026-11-02T10:00:00+01:00\tJane Doe\tfeat: a\tb\n\n'
            )
        ).toEqual([
            {
                hash: 'abc1234',
                date: '2026-11-02T10:00:00+01:00',
                author: 'Jane Doe',
                subject: 'feat: a\tb',
            },
        ]);
    });

    it('tells a build without history', async () => {
        const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'git-log-'));
        const file = path.join(dir, 'git-log.tsv');
        await expect(readGitLog(file)).resolves.toBeNull();
        await fs.writeFile(file, 'abc\t2026-11-02\tJane\tfix: x\n');
        await expect(readGitLog(file)).resolves.toHaveLength(1);
    });
});
