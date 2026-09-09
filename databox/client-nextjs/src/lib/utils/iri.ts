export function iri(entity: string, id: string): string {
    return `/${entity}/${id}`;
}

export function idFromIri<T extends string | null | undefined>(value: T): T {
    if (!value) {
        return value;
    }
    const parts = value.split('/');

    return parts[parts.length - 1] as T;
}

export function isIriOf(entity: string, value: string): boolean {
    return value.startsWith(`/${entity}/`);
}

/**
 * Replaces nested hydra objects by their IRI so a payload can be sent back to
 * the API (API Platform expects IRIs for relations).
 */
export function toIris<T extends Record<string, any>>(
    data: T,
    ignoredKeys: string[] = []
): T {
    const out: Record<string, any> = {};
    for (const [k, v] of Object.entries(data)) {
        if (
            v &&
            typeof v === 'object' &&
            !Array.isArray(v) &&
            '@id' in v &&
            !ignoredKeys.includes(k)
        ) {
            out[k] = v['@id'];
        } else if (Array.isArray(v)) {
            out[k] = v.map(i =>
                i && typeof i === 'object' && '@id' in i ? i['@id'] : i
            );
        } else {
            out[k] = v;
        }
    }

    return out as T;
}
