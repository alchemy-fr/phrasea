import {notFound} from 'next/navigation';

/**
 * The `@modal` catch-all makes every URL match that slot, so an unknown route
 * is no longer unmatched as a whole: Next would render `default.tsx` for the
 * children slot — the assets screen, with a 200 status — instead of falling
 * back to `not-found.tsx`. Matching unknown routes here restores that.
 *
 * Real routes, being more specific, still take precedence over it.
 */
export default function AppCatchAll(): never {
    notFound();
}
