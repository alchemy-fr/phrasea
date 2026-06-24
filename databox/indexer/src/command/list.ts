import {getLocations} from '../locations';
import util from 'util';

export default async function listCommand() {
    const locations = getLocations();

    Object.entries(locations).forEach(([key, l]) => {
        // eslint-disable-next-line no-console
        console.log(`${key}:`);
        // eslint-disable-next-line no-console
        console.log(
            util.inspect(l, {showHidden: false, depth: null, colors: true})
        );
    });
}
