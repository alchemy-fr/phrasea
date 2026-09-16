import {routes} from '@/lib/routes';

const pathOf = (url: string): string => url.split(/[?#]/)[0];

/**
 * Remembers where each route screen (a dialog, a viewer) was opened from, so
 * that closing it can push that URL back.
 *
 * A screen is identified by a key — the URL prefix all of its own URLs share,
 * e.g. `/assets/42/manage` for every tab of that dialog.
 *
 * "The URL we were on before" is not enough: a screen we *return* to (closing
 * a dialog opened from it, or the back button) would record the screen we
 * just left as its origin, and the two would then send the user to each other
 * forever. So an origin is only recorded when the screen is opened fresh, i.e.
 * when it cannot be reached by walking back the origins of the screen we come
 * from.
 */
export class RouteOrigins {
    private readonly origins = new Map<string, string>();
    private readonly screenOfPath = new Map<string, string>();

    /** Declares that `pathname` is displayed by `screen`. */
    register(pathname: string, screen: string): void {
        if (pathname === screen || pathname.startsWith(`${screen}/`)) {
            this.screenOfPath.set(pathname, screen);
        }
    }

    /**
     * Origin of `screen`, now being displayed after `previousUrl` (undefined
     * on a direct load).
     */
    resolve(screen: string, previousUrl: string | undefined): string {
        if (previousUrl === undefined) {
            return this.origins.get(screen) ?? routes.assets();
        }

        const visited = new Set<string>();
        let current = this.screenOfPath.get(pathOf(previousUrl));
        while (current !== undefined && !visited.has(current)) {
            if (current === screen) {
                // Coming back to it (or re-rendering it): keep its origin
                return this.origins.get(screen) ?? routes.assets();
            }
            visited.add(current);
            const origin = this.origins.get(current);
            current = origin
                ? this.screenOfPath.get(pathOf(origin))
                : undefined;
        }

        this.origins.set(screen, previousUrl);

        return previousUrl;
    }
}
