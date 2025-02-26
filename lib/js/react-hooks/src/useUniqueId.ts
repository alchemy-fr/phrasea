import React, {DependencyList} from 'react';

export default function useUniqueId(
    trackingValues: DependencyList = []
): string {
    return React.useMemo(() => makeId(5), trackingValues);
}

function makeId(length) {
    let result = '';
    const characters =
        'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    const charactersLength = characters.length;
    let counter = 0;
    while (counter < length) {
        result += characters.charAt(
            Math.floor(Math.random() * charactersLength)
        );
        counter += 1;
    }
    return result;
}
