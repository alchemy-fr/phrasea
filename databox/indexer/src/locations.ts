import {ConfigOptions, IndexLocation} from './types/config';
import {getConfig} from './configLoader';

const locations: Record<string, IndexLocation<any>> = {};

const locs: IndexLocation<any>[] = getConfig('locations');

locs.forEach(l => {
    locations[l.name] = l;
});

export function getLocation<T extends ConfigOptions = any>(
    name: string
): IndexLocation<T> {
    const location = locations[name] || undefined;
    if (!location) {
        throw new Error(`Unknown location ${name}`);
    }

    return location;
}

export function getLocations() {
    return locations;
}
