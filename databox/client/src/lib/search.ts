function normalizeString(str: string): string {
    return str
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();
}

export function search<T extends Record<string, any>>(
    list: T[],
    props: (keyof T)[],
    query: string
): T[] {
    const normalizedQuery = normalizeString(query);
    return list.filter((item: T) =>
        props.some(prop =>
            normalizeString(String(item[prop])).includes(normalizedQuery)
        )
    );
}
