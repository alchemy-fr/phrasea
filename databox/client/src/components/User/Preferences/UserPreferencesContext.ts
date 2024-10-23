import {ThemeName} from '../../../lib/theme';
import React from 'react';
import {Layout} from '../../AssetList/Layouts';

export type UserPreferences = {
    theme?: ThemeName | undefined;
    pinnedAttrs?: Record<string, string[]> | undefined;
    layout?: Layout;
};

export type UpdatePreferenceHandlerArg<T extends keyof UserPreferences> =
    | ((prev: UserPreferences[T]) => UserPreferences[T])
    | UserPreferences[T]
    | undefined;

export type UpdatePreferenceHandler = <T extends keyof UserPreferences>(
    name: T,
    handler: UpdatePreferenceHandlerArg<T>
) => void;

export type TUserPreferencesContext = {
    preferences: UserPreferences;
    updatePreference: UpdatePreferenceHandler;
};

export const UserPreferencesContext =
    React.createContext<TUserPreferencesContext>({
        preferences: {},
        updatePreference: () => {},
    });
