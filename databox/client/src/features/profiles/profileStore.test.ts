import {beforeEach, describe, expect, it, vi} from 'vitest';
import type {DisplayProfile} from '@/types/api';
import {ProfileItemSection, ProfileItemType} from '@/types/api';

const put = vi.hoisted(() => vi.fn(() => Promise.resolve()));
vi.mock('@/lib/api/http', () => ({api: {put, get: vi.fn()}}));

const api = vi.hoisted(() => ({
    getProfiles: vi.fn(),
    getProfile: vi.fn(),
    getUserDefaultProfile: vi.fn(),
    addToProfile: vi.fn(),
    deleteProfile: vi.fn(),
    putProfile: vi.fn(),
    putProfileItem: vi.fn(),
    removeFromProfile: vi.fn(),
    sortProfileItems: vi.fn(),
}));
vi.mock('@/lib/api/misc', () => api);

const {useProfileStore} = await import('./profileStore');
const {usePreferencesStore} = await import('@/features/preferences/store');

function profile(
    id: string,
    data: Record<string, unknown> = {},
    edit = false
): DisplayProfile {
    return {
        '@id': `/profiles/${id}`,
        '@type': 'profile',
        'id': id,
        'name': `Profile ${id}`,
        'items': [],
        'data': data,
        'createdAt': '',
        'updatedAt': '',
        'capabilities': {
            edit,
            delete: edit,
            editPermissions: edit,
            share: false,
        },
    };
}

const userDefault = profile('default', {
    layout: 'masonry',
    display: {thumbSize: 300},
    // Not a synced preference: not applied
    palette: 'ocean',
});

describe('profile store: default profile of the user', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.getProfiles.mockResolvedValue({items: [], total: 0});
        api.getUserDefaultProfile.mockResolvedValue(userDefault);
        useProfileStore.setState({
            profiles: [],
            current: undefined,
            userDefault: undefined,
            currentIsDefault: false,
            loaded: false,
            loading: false,
        });
        // Forgets the values of the fallback of the previous test
        usePreferencesStore.getState().applyFallback({});
        usePreferencesStore.setState({
            preferences: {},
            authenticated: true,
        });
    });

    it('applies the default profile without chosen one', async () => {
        usePreferencesStore.setState({
            preferences: {profile: null, layout: 'list'},
        });

        await useProfileStore.getState().load();

        const state = useProfileStore.getState();
        expect(state.current?.id).toBe('default');
        expect(state.currentIsDefault).toBe(true);
        expect(state.userDefault?.id).toBe('default');
        // Only the preferences the user has not set, and nothing saved
        expect(usePreferencesStore.getState().preferences).toEqual({
            profile: null,
            layout: 'list',
            display: {thumbSize: 300},
        });
        expect(put).not.toHaveBeenCalled();
    });

    it('keeps the chosen profile', async () => {
        usePreferencesStore.setState({preferences: {profile: 'chosen'}});
        api.getProfile.mockResolvedValue(profile('chosen'));

        await useProfileStore.getState().load();

        const state = useProfileStore.getState();
        expect(state.current?.id).toBe('chosen');
        expect(state.currentIsDefault).toBe(false);
        // Named by the "default" entry
        expect(state.userDefault?.id).toBe('default');
        expect(usePreferencesStore.getState().preferences).toEqual({
            profile: 'chosen',
        });
    });

    it('falls back to the default when the chosen profile is not readable', async () => {
        usePreferencesStore.setState({preferences: {profile: 'gone'}});
        api.getProfile.mockRejectedValue(new Error('403'));

        await useProfileStore.getState().load();

        expect(useProfileStore.getState().current?.id).toBe('default');
        expect(useProfileStore.getState().currentIsDefault).toBe(true);
    });

    it('has no profile without default', async () => {
        api.getUserDefaultProfile.mockResolvedValue(undefined);

        await useProfileStore.getState().load();

        expect(useProfileStore.getState().current).toBeUndefined();
        expect(useProfileStore.getState().currentIsDefault).toBe(false);
        expect(usePreferencesStore.getState().preferences).toEqual({});
    });

    it('goes back to the default when "default" is chosen', async () => {
        usePreferencesStore.setState({
            preferences: {profile: 'chosen', layout: 'list'},
        });
        api.getProfile.mockResolvedValue(profile('chosen'));
        await useProfileStore.getState().load();

        await useProfileStore.getState().setCurrent(undefined);

        expect(useProfileStore.getState().current?.id).toBe('default');
        expect(useProfileStore.getState().currentIsDefault).toBe(true);
        expect(usePreferencesStore.getState().preferences).toEqual({
            profile: null,
            layout: 'masonry',
            display: {thumbSize: 300},
        });
        // Only the choice is saved
        expect(put).toHaveBeenCalledTimes(1);
        expect(put).toHaveBeenCalledWith(
            '/preferences',
            {name: 'profile', value: null, reset: true},
            undefined
        );
    });

    it('does not change a default profile the user may not edit', async () => {
        await useProfileStore.getState().load();

        await useProfileStore.getState().addItems([
            {
                id: '',
                section: ProfileItemSection.Attributes,
                type: ProfileItemType.BuiltIn,
                key: 'createdAt',
            },
        ]);

        expect(api.addToProfile).not.toHaveBeenCalled();
    });

    it('applies a new default while none is chosen', async () => {
        usePreferencesStore.setState({preferences: {profile: null}});
        await useProfileStore.getState().load();

        api.getUserDefaultProfile.mockResolvedValue(profile('other'));
        await useProfileStore.getState().reloadUserDefault();
        expect(useProfileStore.getState().current?.id).toBe('other');
        expect(useProfileStore.getState().currentIsDefault).toBe(true);

        api.getUserDefaultProfile.mockResolvedValue(undefined);
        await useProfileStore.getState().reloadUserDefault();
        expect(useProfileStore.getState().current).toBeUndefined();
        expect(useProfileStore.getState().currentIsDefault).toBe(false);
    });

    it('applies a default marked while none applied', async () => {
        usePreferencesStore.setState({preferences: {profile: null}});
        api.getUserDefaultProfile.mockResolvedValue(undefined);
        await useProfileStore.getState().load();
        expect(useProfileStore.getState().current).toBeUndefined();

        api.getUserDefaultProfile.mockResolvedValue(userDefault);
        await useProfileStore.getState().reloadUserDefault();

        expect(useProfileStore.getState().current?.id).toBe('default');
        expect(useProfileStore.getState().currentIsDefault).toBe(true);
        expect(usePreferencesStore.getState().preferences).toEqual({
            profile: null,
            layout: 'masonry',
            display: {thumbSize: 300},
        });
    });

    it('replaces the preferences of the previous default', async () => {
        usePreferencesStore.setState({preferences: {profile: null}});
        await useProfileStore.getState().load();
        // A choice of the user stays
        await usePreferencesStore
            .getState()
            .updatePreference('display', {thumbSize: 120} as any);

        api.getUserDefaultProfile.mockResolvedValue(
            profile('other', {layout: 'list', facets: [{name: 'f'}]})
        );
        await useProfileStore.getState().reloadUserDefault();
        expect(usePreferencesStore.getState().preferences).toEqual({
            profile: null,
            layout: 'list',
            display: {thumbSize: 120},
            facets: [{name: 'f'}],
        });

        api.getUserDefaultProfile.mockResolvedValue(undefined);
        await useProfileStore.getState().reloadUserDefault();
        expect(usePreferencesStore.getState().preferences).toEqual({
            profile: null,
            display: {thumbSize: 120},
        });
    });

    it('replaces the preferences of the default once "default" is chosen', async () => {
        usePreferencesStore.setState({preferences: {profile: 'chosen'}});
        api.getProfile.mockResolvedValue(profile('chosen'));
        await useProfileStore.getState().load();
        await useProfileStore.getState().setCurrent(undefined);

        api.getUserDefaultProfile.mockResolvedValue(
            profile('other', {layout: 'list'})
        );
        await useProfileStore.getState().reloadUserDefault();

        expect(usePreferencesStore.getState().preferences).toEqual({
            profile: null,
            layout: 'list',
        });
    });

    it('keeps the chosen profile when the default changes', async () => {
        usePreferencesStore.setState({preferences: {profile: 'chosen'}});
        api.getProfile.mockResolvedValue(profile('chosen'));
        await useProfileStore.getState().load();

        api.getUserDefaultProfile.mockResolvedValue(profile('other'));
        await useProfileStore.getState().reloadUserDefault();
        expect(useProfileStore.getState().current?.id).toBe('chosen');
        expect(useProfileStore.getState().userDefault?.id).toBe('other');
    });

    it('does not sync the preferences into the default profile', async () => {
        api.getUserDefaultProfile.mockResolvedValue(
            profile('default', {}, true)
        );
        await useProfileStore.getState().load();

        await useProfileStore.getState().syncPreferences();

        expect(api.putProfile).not.toHaveBeenCalled();
    });
});
