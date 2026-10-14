import {API_URL, CLIENT_ID, CLIENT_SECRET, SLUG} from './env';
import {SRC_DIR} from './paths';
import {PREFIX} from './fixtures';

/**
 * The `config.e2e.json` handed to the indexer.
 *
 * `ownerId` is resolved at provisioning time and written in as a literal, so
 * the indexer container needs nothing but `CONFIG_FILE` and `--workdir /e2e`.
 * The two `%env(...)%` placeholders are kept on purpose: resolving them is
 * production code (configLoader.replaceEnv), and the `bool:` / `int:` casts
 * are part of what this suite exercises.
 */
export function buildIndexerConfig(ownerId: string): object {
    return {
        databox: {
            url: API_URL,
            clientId: CLIENT_ID,
            clientSecret: CLIENT_SECRET,
            ownerId,
            verifySSL: '%env(bool:DATABOX_VERIFY_SSL)%',
            concurrency: '%env(int:DATABOX_CONCURRENCY)%',
        },
        blacklist: ['(^|/)\\..+$'],
        locations: [
            {
                name: 'e2e_fs',
                type: 'fs',
                options: {
                    dir: SRC_DIR,
                    dirPrefix: PREFIX,
                    sourceDir: SRC_DIR,
                    workspaceSlug: SLUG,
                    // Deliberately false: with `createNewWorkspace` the second
                    // run would flush the workspace first and the idempotence
                    // assertions would test nothing.
                    createNewWorkspace: false,
                },
                alternateUrls: [
                    {
                        name: 'indexer',
                        pathPattern: 'indexer://${sourcePath}',
                    },
                ],
            },
        ],
    };
}
