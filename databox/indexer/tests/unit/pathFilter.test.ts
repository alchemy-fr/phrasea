import {passFilters} from '../../src/pathFilter';
import {Asset} from '../../src/indexers';
import {createTestLogger} from '../helpers/logger';

const asset = (path: string) => ({path, key: path}) as Asset;

/**
 * The fixture config declares:
 *   whitelist: ["\\.(jpe?g|png|txt)$"]
 *   blacklist: ["(^|/)\\..+$"]
 */
describe('passFilters', () => {
    it('accepts a path matching the whitelist and not the blacklist', () => {
        expect(passFilters(asset('a/b.jpg'), createTestLogger())).toBe(true);
        expect(passFilters(asset('root.txt'), createTestLogger())).toBe(true);
    });

    it('rejects a path that matches no whitelist entry', () => {
        const logger = createTestLogger();

        expect(passFilters(asset('a/b.pdf'), logger)).toBe(false);
        expect(logger.hasMessageMatching('does not match whitelist')).toBe(
            true
        );
    });

    it('rejects a blacklisted path even when whitelisted', () => {
        const logger = createTestLogger();

        expect(passFilters(asset('a/.hidden.txt'), logger)).toBe(false);
        // The e2e suite greps for this exact string to prove the blacklisted
        // files were walked and then filtered.
        expect(logger.hasMessageMatching('matches blacklist')).toBe(true);
    });

    it('rejects a hidden file at the root', () => {
        expect(passFilters(asset('.hidden.txt'), createTestLogger())).toBe(
            false
        );
    });

    it('rejects a file under a hidden directory', () => {
        expect(
            passFilters(asset('a/.git/config.txt'), createTestLogger())
        ).toBe(false);
    });
});

describe('passFilters with other filter configurations', () => {
    const load = async (filters: {
        whitelist?: string[] | null;
        blacklist?: string[] | null;
    }) => {
        vi.resetModules();
        vi.doMock('../../src/configLoader', () => ({
            getConfig: (key: string, defaultValue: any) =>
                key in filters
                    ? (filters as Record<string, any>)[key]
                    : defaultValue,
        }));

        return (await import('../../src/pathFilter')).passFilters;
    };

    afterEach(() => {
        vi.doUnmock('../../src/configLoader');
        vi.resetModules();
    });

    it('accepts everything when neither list is configured', async () => {
        const fresh = await load({whitelist: null, blacklist: null});

        expect(fresh(asset('.hidden'), createTestLogger())).toBe(true);
        expect(fresh(asset('anything.xyz'), createTestLogger())).toBe(true);
    });

    it('applies a whitelist alone', async () => {
        const fresh = await load({whitelist: ['^keep/'], blacklist: null});

        expect(fresh(asset('keep/a.txt'), createTestLogger())).toBe(true);
        expect(fresh(asset('drop/a.txt'), createTestLogger())).toBe(false);
    });

    it('applies a blacklist alone', async () => {
        const fresh = await load({whitelist: null, blacklist: ['\\.tmp$']});

        expect(fresh(asset('a.txt'), createTestLogger())).toBe(true);
        expect(fresh(asset('a.tmp'), createTestLogger())).toBe(false);
    });

    it('accepts a path matching any whitelist entry', async () => {
        const fresh = await load({
            whitelist: ['^a/', '^b/'],
            blacklist: null,
        });

        expect(fresh(asset('a/x'), createTestLogger())).toBe(true);
        expect(fresh(asset('b/x'), createTestLogger())).toBe(true);
        expect(fresh(asset('c/x'), createTestLogger())).toBe(false);
    });
});
