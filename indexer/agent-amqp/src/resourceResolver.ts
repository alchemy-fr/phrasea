import {getEnvStrict} from "./env";

export function generatePublicUrl(path: string): string {
    return `${getEnvStrict('PUBLIC_URL')}/assets/?path=${encodeURIComponent(path)}`;
}
