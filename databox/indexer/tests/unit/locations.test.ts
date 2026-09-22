import {getLocation, getLocations} from '../../src/locations';

describe('getLocations', () => {
    it('indexes every configured location by name', () => {
        expect(Object.keys(getLocations()).sort()).toEqual([
            'fs_served',
            'fs_test',
            's3_test',
            'unset_test',
        ]);
    });
});

describe('getLocation', () => {
    it('returns the location with its type and options', () => {
        const location = getLocation('fs_test');

        expect(location.name).toEqual('fs_test');
        expect(location.type).toEqual('fs');
        expect(location.options.workspaceSlug).toEqual('test-workspace');
    });

    it('exposes per-location alternateUrls', () => {
        expect(getLocation('fs_test').alternateUrls).toEqual([
            {name: 'indexer', pathPattern: 'indexer://${sourcePath}'},
        ]);
        expect(getLocation('s3_test').alternateUrls).toBeUndefined();
    });

    it('throws for an unknown name', () => {
        expect(() => getLocation('nope')).toThrow('Unknown location nope');
    });
});
