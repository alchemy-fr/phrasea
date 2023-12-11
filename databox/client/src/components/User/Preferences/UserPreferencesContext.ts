import {LayoutEnum} from '../../Media/Search/Pager';
import {ThemeName} from '../../../lib/theme';
import React from 'react';

export type UserPreferences = {
    theme?: ThemeName | undefined;
    pinnedAttrs?: Record<string, string[]> | undefined;
    layout?: LayoutEnum;
};

export type UpdatePreferenceHandler = <T extends keyof UserPreferences>(
    name: T,
    handler:
        | ((prev: UserPreferences[T]) => UserPreferences[T])
        | UserPreferences[T]
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
