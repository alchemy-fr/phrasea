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

    it('opens an asset in the quarantine screen', () => {
        expect(resolveNotificationUri('/quarantine/a1')).toBe(
            '/quarantine?asset=a1'
        );
    });

    it('opens a tab of the workspace management', () => {
        expect(
            resolveNotificationUri('/workspaces/w1/manage/integrations')
        ).toBe('/workspaces/w1/manage/integrations');
    });

    it('opens an integration of a workspace', () => {
        expect(
            resolveNotificationUri('/workspaces/w1/manage/integrations/i1')
        ).toBe('/workspaces/w1/manage/integrations/i1');
    });

    it('opens an attribute definition of a workspace', () => {
        expect(
            resolveNotificationUri('/workspaces/w1/manage/attributes/a1')
        ).toBe('/workspaces/w1/manage/attributes/a1');
    });

    it('opens an integration of the instance', () => {
        expect(resolveNotificationUri('/admin/integrations/i1')).toBe(
            '/admin/integrations/i1'
        );
    });

    it('falls back to the search on an unknown URI', () => {
        expect(resolveNotificationUri('/foo/bar/baz')).toBe('/assets');
    });

    it('falls back to the search', () => {
        expect(resolveNotificationUri('/unknown')).toBe('/assets');
    });
});
