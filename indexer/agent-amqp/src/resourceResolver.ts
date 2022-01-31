import {getEnvStrict} from "./env";

export function generatePublicUrl(path: string, source: string, query: Record<string, string> = {}): string {
    query.path = path;
    query.source = path;

    return `${getEnvStrict('PUBLIC_URL')}/assets/?${new URLSearchParams(query).toString()}`;
}
