import apiClient from './api-client';
import {ApiCollectionResponse, getHydraCollection} from './hydra.ts';
import {SelectOption} from '@alchemy/react-form';
import i18n from '../i18n.ts';

export type Locale = {
    id: string;
    language: string;
    region: string;
    script: string;
    name: string;
    nativeName: string;
};

type LocaleAwareCache = Record<string, Locale[]>;

const cache: LocaleAwareCache = {};

export async function getLocaleOptions(): Promise<SelectOption[]> {
    const locales = await getLocales();

    return locales.map(l => ({
        label: l.name,
        value: l.id,
    }));
}

export async function getLocales(): Promise<Locale[]> {
    const currentLocale = i18n.language;
    if (cache[currentLocale]) {
        return cache[currentLocale];
    }

    const res = await doGetLocales();

    return (cache[currentLocale] = res.result);
}

async function doGetLocales(): Promise<ApiCollectionResponse<Locale>> {
    const res = await apiClient.get('/locales');

    return getHydraCollection(res.data);
}
