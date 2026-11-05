/**
 * Session storage key of the attribute definitions cache (see
 * `definitionsCache.ts`). Kept apart so that `lib/auth` can purge it without
 * importing the store (and the API client, which imports `lib/auth`).
 */
export const DEFINITIONS_CACHE_KEY = 'dbx.attribute-definitions';
