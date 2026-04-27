import {create} from 'zustand';
import {
    AttributeDefinition,
    Profile,
    ProfileItem,
    ProfileItemSection,
    ProfileItemType,
} from '../types';
import {
    addToProfile,
    deleteProfile,
    getProfile,
    GetProfileOptions,
    getProfiles,
    putProfile,
    removeFromProfile,
    sortProfileItems,
} from '../api/profile.ts';
import {replaceList} from './storeUtils.ts';
import {
    UserPreferences,
    useUserPreferencesStore,
} from './userPreferencesStore.ts';
import {deepEquals} from '@alchemy/core';

type State = {
    profiles: Profile[];
    current: Profile | undefined;
    nextUrl?: string | undefined;
    loaded: boolean;
    loading: boolean;
    loadingCurrent: boolean;
    loadingMore: boolean;
    total?: number;
    hasMore: () => boolean;
    load: (params?: GetProfileOptions, force?: boolean) => Promise<void>;
    loadMore: () => Promise<void>;
    addProfile: (profile: Profile) => void;
    loadProfile: (id: string) => Promise<Profile>;
    updateProfile: (profile: Profile) => void;
    syncData: () => Promise<void>;
    arePreferencesSynced: (profile: Profile) => Promise<boolean>;
    updateProfileItem: (profileId: string, data: ProfileItem) => void;
    deleteProfile: (id: string) => void;
    addToCurrent: (items: ProfileItem[]) => void;
    addToList: (profileId: string | undefined, items: ProfileItem[]) => void;
    sortList: (profileId: string, items: string[]) => void;
    toggleDefinition: (definition: AttributeDefinition) => void;
    removeFromProfile: (profileId: string, ids: string[]) => void;
    setCurrent: (id: string | undefined) => Promise<void>;
    loadCurrent: (id: string) => Promise<void>;
    shouldSelectProfile: () => boolean;
};

export const useProfileStore = create<State>((set, getState) => ({
    loadingMore: false,
    loaded: false,
    loading: false,
    loadingCurrent: false,
    current: undefined,
    profiles: [],

    load: async (params, force) => {
        const currentState = getState();
        if ((currentState.loaded || currentState.loading) && !force) {
            return;
        }

        set({
            loading: true,
        });

        const preferences = await useUserPreferencesStore.getState().load();
        const prefProfile = preferences['profile'];

        try {
            const data = await getProfiles(params);
            let current = currentState.current;

            if (prefProfile && !current) {
                try {
                    current = await getProfile(prefProfile);
                } catch (e) {
                    current = undefined;
                }
            }

            set(state => {
                const previousCurrent = prefProfile
                    ? state.profiles.find(at => at.id === prefProfile)
                    : undefined;
                const newList = preserveListItems(state.profiles, data.result);
                if (
                    previousCurrent &&
                    !newList.some(i => i.id === previousCurrent?.id)
                ) {
                    newList.concat(previousCurrent);
                }

                return {
                    profiles: newList,
                    total: data.total,
                    loading: false,
                    loaded: true,
                    nextUrl: data.next || undefined,
                    current,
                };
            });
        } catch (e: any) {
            set({loading: false});
            throw e;
        }
    },

    hasMore() {
        return !!getState().nextUrl;
    },

    setCurrent: async id => {
        const updatePref = (value: UserPreferences['profile']) =>
            useUserPreferencesStore
                .getState()
                .updatePreference('profile', value);

        if (!id) {
            set({
                current: undefined,
                loadingCurrent: false,
            });

            await updatePref(null);

            return;
        }

        if (getState().current?.id === id) {
            return;
        }

        const data = getState().profiles.find(l => l.id === id);

        set({
            current: data,
            loadingCurrent: true,
        });

        try {
            const profile = await getProfile(id);
            set({
                current: profile,
                loadingCurrent: false,
            });
            await updatePref(id);
        } catch (e: any) {
            await updatePref(null);
            set({
                current: undefined,
                loadingCurrent: false,
            });
        }
    },

    loadCurrent: async id => {
        const currentState = getState();
        if (currentState.loadingCurrent || currentState.current?.id === id) {
            return;
        }

        const data = currentState.profiles.find(l => l.id === id);

        set({
            current: data,
            loadingCurrent: true,
        });

        try {
            const profile = await getProfile(id);
            set({
                current: profile,
                loadingCurrent: false,
            });
        } catch (e: any) {
            set({
                current: undefined,
                loadingCurrent: false,
            });
        }
    },

    shouldSelectProfile: () => {
        const {current, loading, profiles} = getState();

        if (current) {
            return false;
        }

        if (loading) {
            return true;
        }

        return profiles.length > 1;
    },

    updateProfile: data => {
        set(state => ({
            profiles: state.profiles.map(b => {
                if (b.id === data.id) {
                    return {
                        ...b,
                        ...data,
                    };
                }

                return b;
            }),
            current: state.current?.id === data.id ? data : state.current,
        }));
    },

    syncData: async () => {
        const data = await useUserPreferencesStore.getState().load();

        set(state => {
            if (!state.current) {
                return state;
            }

            putProfile(state.current!.id, {data});

            return {
                profiles: state.profiles.map(b => {
                    if (b.id === state.current!.id) {
                        return {
                            ...b,
                            data,
                        };
                    }

                    return b;
                }),
                current: {
                    ...state.current,
                    data,
                },
            };
        });
    },

    arePreferencesSynced: async profile => {
        const data = await useUserPreferencesStore.getState().load();

        return deepEquals(profile.data, data);
    },

    updateProfileItem: (profileId, item) => {
        const replaceItemInList = (l: Profile, item: ProfileItem): Profile => {
            return {
                ...l,
                items: l.items?.map(i => {
                    return i.id === item.id
                        ? {
                              ...i,
                              ...item,
                          }
                        : i;
                }),
            };
        };

        set(state => ({
            profiles: state.profiles.map(l => {
                if (l.id === profileId) {
                    return replaceItemInList(l, item);
                }

                return l;
            }),
            current: state.current
                ? replaceItemInList(state.current, item)
                : undefined,
        }));
    },

    loadMore: async () => {
        const nextUrl = getState().nextUrl;
        if (!nextUrl) {
            return;
        }

        set({loadingMore: true});
        try {
            const data = await getProfiles({nextUrl});

            set(state => ({
                profiles: preserveListItems(
                    state.profiles,
                    state.profiles.concat(data.result)
                ),
                total: data.total,
                loadingMore: false,
                nextUrl: data.next || undefined,
            }));
        } catch (e: any) {
            set({loadingMore: false});

            throw e;
        }
    },

    addProfile(data) {
        set(state => ({
            profiles: [data].concat(state.profiles),
        }));
    },

    deleteProfile: async id => {
        await deleteProfile(id);

        set(state => ({
            profiles: state.profiles.filter(b => b.id !== id),
            current: state.current?.id === id ? undefined : state.current,
        }));
    },

    toggleDefinition: definition => {
        const state = getState();
        const current = state.current;
        const defId = definition.id;

        if (current) {
            const item = current.items!.find(
                i => i.definition === defId || i.key === defId
            );
            if (item?.id) {
                state.removeFromProfile(current.id, [item.id]);

                return;
            }
        }

        state.addToCurrent([attributeDefinitionToItem(definition)]);
    },

    loadProfile: async (id: string) => {
        try {
            const profile = await getProfile(id!);
            set(state => {
                return {
                    current:
                        state.current?.id === profile.id
                            ? profile
                            : state.current,
                    profiles: replaceList(state.profiles, profile),
                };
            });

            return profile;
        } catch (e: any) {
            const s = getState();
            if (s.current?.id === id) {
                s.setCurrent(undefined);
            }
            throw e;
        }
    },

    addToList: async (profileId, items) => {
        try {
            const profile = await addToProfile(profileId, {
                // @ts-expect-error id cannot be undefined
                items: items.map(i => ({
                    ...i,
                    id: isTmpId(i.id ?? '') ? undefined : i.id,
                })),
            });
            set(state => ({
                current: profile,
                profiles: replaceList(state.profiles, profile),
            }));
        } catch (e: any) {
            if (profileId) {
                set(state => {
                    if (state.current?.id === profileId) {
                        const curr = state.current!;

                        return {
                            current: {
                                ...curr,
                                items: curr.items,
                            },
                        };
                    }

                    return state;
                });
            }
        }
    },

    addToCurrent: async items => {
        const state = getState();
        state.addToList(state.current?.id, items);
    },

    sortList: async (profileId, items) => {
        set(p => ({
            profiles: p.profiles.map(b => {
                if (b.id === profileId && b.items) {
                    return getReorderedListItems(b, items);
                }

                return b;
            }),
            current:
                p.current?.id === profileId
                    ? getReorderedListItems(p.current, items)
                    : p.current,
        }));

        await sortProfileItems(profileId, items);
    },

    removeFromProfile: async (profileId, items) => {
        let current: Profile | undefined = getState().current;
        if (current && current.id !== profileId) {
            current = undefined;
        }

        if (current && current.items !== undefined) {
            set({
                current: {
                    ...current,
                    items: current.items.filter(
                        d => !items.some(i => i === d.id)
                    ),
                },
            });
        }

        const itemsToRemove = items.filter(i => !isTmpId(i));
        if (itemsToRemove.length > 0) {
            const profile = await removeFromProfile(profileId, itemsToRemove);
            set(state => ({
                profiles: replaceList(state.profiles, profile),
            }));
        }
    },
}));

export function attributeDefinitionToItem(
    definition: AttributeDefinition
): ProfileItem {
    const isBI = definition.builtIn;

    return {
        id: tmpIdPrefix + definition.id,
        section: ProfileItemSection.Attributes,
        type: isBI ? ProfileItemType.BuiltIn : ProfileItemType.Definition,
        definition: isBI ? undefined : definition.id,
        key: isBI ? definition.id : undefined,
    };
}

let inc = 1;

function generateId(): string {
    return tmpIdPrefix + (inc++).toString();
}

export function createDivider(title: string): ProfileItem {
    return {
        id: generateId(),
        section: ProfileItemSection.Attributes,
        type: ProfileItemType.Divider,
        key: title,
    };
}

export function createSpacer(): ProfileItem {
    return {
        id: generateId(),
        section: ProfileItemSection.Attributes,
        type: ProfileItemType.Spacer,
    };
}

const tmpIdPrefix = '_tmp_';

export function isTmpId(id: string): boolean {
    return id.startsWith(tmpIdPrefix);
}

export function hasDefinitionInItems(
    items: ProfileItem[],
    id: string
): boolean {
    return items.some(i => i.definition === id || i.key === id);
}

function getReorderedListItems(profile: Profile, order: string[]): Profile {
    if (!profile.items) {
        return profile;
    }

    return {
        ...profile,
        items: order
            .map(id => profile.items!.find(i => i.id === id))
            .filter(i => !!i),
    };
}

function preserveListItems(prev: Profile[], list: Profile[]): Profile[] {
    return list.map(i => {
        const prevItem = prev.find(p => p.id === i.id);
        if (prevItem) {
            return {
                ...i,
                items: prevItem.items ?? i.items,
            };
        }

        return i;
    });
}
