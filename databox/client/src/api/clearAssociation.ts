
type Data = Record<string, any>;

export function clearAssociationIds<T extends Data>(data: T, level = 0): T {
    const newData: T = {} as T;

    Object.keys(data).filter(k => level === 0 || k !== 'id').map((k: keyof T) => {
        const d = data[k];

        if (d && typeof d === 'object') {
            newData[k] = clearAssociationIds<typeof d>(d, level + 1);
        } else {
            newData[k] = d;
        }
    });

    return newData;
}
