import {describe, expect, it} from 'vitest';
import {resolveNotificationUri} from './NotificationUriListener';

describe('resolveNotificationUri', () => {
    it('opens a saved search', () => {
        expect(resolveNotificationUri('/saved-searches/s1')).toBe(
            '/assets?id=s1'
        );
    });

    it('opens an asset', () => {
        expect(resolveNotificationUri('/assets/a1')).toBe('/assets/a1/_');
    });

    it('falls back to the search', () => {
        expect(resolveNotificationUri('/unknown')).toBe('/assets');
    });
});
