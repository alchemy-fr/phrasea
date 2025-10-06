export function search<T extends Record<string, any>>(
    list: T[],
    props: (keyof T)[],
    query: string
): T[] {
    const lowerQuery = query.toLowerCase();
    return list.filter((item: T) =>
        props.some(prop =>
            String(item[prop]).toLowerCase().includes(lowerQuery)
        )
    );
}
