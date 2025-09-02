import {useUserPreferencesStore} from './userPreferencesStore.ts';
import apiClient from '../api/api-client.ts';

export function useDataLocale() {
    return useUserPreferencesStore(state => state.preferences)?.dataLocale;
}

export function useUpdateDataLocale() {
    const updatePreference = useUserPreferencesStore(s => s.updatePreference);

    return (locale: string | undefined) => {
        updatePreference('dataLocale', locale);
        apiClient.defaults.headers.common['Data-Locale'] = locale;
    };
}
