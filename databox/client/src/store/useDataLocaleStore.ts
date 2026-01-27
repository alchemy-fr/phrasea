import {useUserPreferencesStore} from './userPreferencesStore.ts';
import {apiClient} from '../init.ts';

export function useDataLocale() {
    return useUserPreferencesStore(state => state.preferences)?.dataLocale;
}

export function useUpdateDataLocale() {
    const updatePreference = useUserPreferencesStore(s => s.updatePreference);

    return async (locale: string | undefined) => {
        await updatePreference('dataLocale', locale);
        updateClientDataLocale(locale);
    };
}

export function updateClientDataLocale(locale: string | undefined) {
    apiClient.defaults.headers.common['X-Data-Locale'] = locale;
}
