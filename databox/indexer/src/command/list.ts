import {getLocations} from '../locations';
import util from 'util';

export default async function listCommand() {
    const locations = getLocations();

    Object.entries(locations).forEach(([key, l]) => {
        console.log(`${key}:`);
        console.log(
            util.inspect(l, {showHidden: false, depth: null, colors: true})
        );
    });
}
