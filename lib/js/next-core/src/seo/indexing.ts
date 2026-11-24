import {isFalse, isTrue} from '../server/http';

/**
 * Whether the search engines may index the client (`ALLOW_INDEXING` of its
 * container), `defaultValue` when the variable is not set or not a boolean.
 */
export function isIndexingAllowed(
    env: Record<string, string | undefined>,
    defaultValue: boolean
): boolean {
    const value = env.ALLOW_INDEXING?.trim();
    if (isTrue(value)) {
        return true;
    }
    if (isFalse(value)) {
        return false;
    }

    return defaultValue;
}
