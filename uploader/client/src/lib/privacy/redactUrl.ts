import {createUrlRedactor} from '@alchemy/core/privacy/redactUrl';

/**
 * Masks the secrets of the URLs before they are sent to Matomo or Sentry:
 * the token of an upload link lets anyone upload.
 */
export const redactUrl = createUrlRedactor([
    {pattern: /^\/l\/[^/?#]+/, replacement: '/l/[token]'},
]);
