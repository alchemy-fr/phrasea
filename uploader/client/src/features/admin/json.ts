export type JsonParseResult<T> =
    {value: T; error?: undefined} | {value?: undefined; error: string};

/** Parses the JSON typed in an editor; only a JSON object is accepted */
export function parseJsonObject<T = Record<string, unknown>>(
    text: string
): JsonParseResult<T> {
    try {
        const value: unknown = JSON.parse(text);
        if (!value || typeof value !== 'object' || Array.isArray(value)) {
            return {error: 'A JSON object is expected'};
        }

        return {value: value as T};
    } catch (e) {
        return {error: e instanceof Error ? e.message : String(e)};
    }
}

export function toJson(value: unknown): string {
    return JSON.stringify(value ?? {}, null, 2);
}
