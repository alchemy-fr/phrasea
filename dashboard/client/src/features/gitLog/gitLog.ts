import 'server-only';
import fs from 'node:fs/promises';
import path from 'node:path';

/**
 * The last commits of the build, written by bin/git-log.sh before the image
 * is built (one commit per line: hash, ISO date, author, subject, separated
 * by tabs). Absent from an image built without it.
 */
export const DEFAULT_GIT_LOG_SRC = 'build-info/git-log.tsv';

export type Commit = {
    hash: string;
    date: string;
    author: string;
    subject: string;
};

export function parseGitLog(content: string): Commit[] {
    return content.split('\n').flatMap(line => {
        const [hash, date, author, ...subject] = line.split('\t');
        if (!hash?.trim() || !date) {
            return [];
        }

        return [
            {
                hash: hash.trim(),
                date,
                author: author ?? '',
                subject: subject.join('\t'),
            },
        ];
    });
}

/** Null when the build carries no commit history */
export async function readGitLog(
    file: string = process.env.GIT_LOG_SRC ||
        path.join(process.cwd(), DEFAULT_GIT_LOG_SRC)
): Promise<Commit[] | null> {
    try {
        // Not traced into the build: the Dockerfile copies build-info/
        return parseGitLog(
            await fs.readFile(/* turbopackIgnore: true */ file, 'utf8')
        );
    } catch (e) {
        if ((e as NodeJS.ErrnoException).code === 'ENOENT') {
            return null;
        }
        throw e;
    }
}
