import {describe, expect, it} from 'vitest';
import {RouteOrigins} from './routeOrigins';

const asset = '/assets/fa44/manage';
const file = '/files/1898/manage';

/** Simulates a screen mounting at `url` after `previous`. */
function open(
    origins: RouteOrigins,
    screen: string,
    url: string,
    previous: string | undefined
): string {
    const origin = origins.resolve(screen, previous);
    origins.register(url, screen);

    return origin;
}

describe('RouteOrigins', () => {
    it('does not loop between a dialog and the dialog opened from it', () => {
        const o = new RouteOrigins();
        expect(open(o, asset, `${asset}/open`, '/assets?q=x')).toBe(
            '/assets?q=x'
        );
        expect(open(o, file, `${file}/info`, `${asset}/open`)).toBe(
            `${asset}/open`
        );

        // Closing the file dialog brings the asset dialog back...
        expect(open(o, asset, `${asset}/open`, `${file}/info`)).toBe(
            '/assets?q=x'
        );
        // ...which then closes to the search, not to the file dialog
        expect(open(o, file, `${file}/info`, `${asset}/open`)).toBe(
            `${asset}/open`
        );
    });

    it('keeps the origin when coming back through the history', () => {
        const o = new RouteOrigins();
        open(o, asset, `${asset}/open`, '/assets');
        open(o, file, `${file}/info`, `${asset}/versions`);
        o.register(`${asset}/versions`, asset);

        // Back from the file dialog to another tab of the asset dialog
        expect(open(o, asset, `${asset}/versions`, `${file}/info`)).toBe(
            '/assets'
        );
    });

    it('records a new origin when the dialog is opened again from elsewhere', () => {
        const o = new RouteOrigins();
        open(o, asset, `${asset}/open`, '/assets');

        expect(open(o, asset, `${asset}/open`, '/collections/7')).toBe(
            '/collections/7'
        );
    });

    it('falls back to the assets screen on a direct load', () => {
        expect(new RouteOrigins().resolve(asset, undefined)).toBe('/assets');
    });

    it('ignores URLs that do not belong to the screen', () => {
        const o = new RouteOrigins();
        o.register('/assets/fa440/manage/info', asset);
        open(o, file, `${file}/info`, '/assets');

        // `/assets/fa440/...` was not taken for the asset dialog
        expect(
            open(o, asset, `${asset}/info`, '/assets/fa440/manage/info')
        ).toBe('/assets/fa440/manage/info');
    });
});
