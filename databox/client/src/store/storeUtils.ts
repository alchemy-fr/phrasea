type Entity = {id: string};

export function replaceList<T extends Entity>(prev: T[], item: T): T[] {
    return prev.some(l => l.id === item.id)
        ? prev.map(l => (l.id === item.id ? item : l))
        : prev.concat([item]);
}
