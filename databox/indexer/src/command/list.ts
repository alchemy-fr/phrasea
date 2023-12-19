import {getLocations} from '../locations';

export default async function listCommand() {
    const locations = getLocations();

    Object.entries(locations).forEach(([key, l]) => {
        console.log(`${key}:`);
        console.log(l);
    });
}
