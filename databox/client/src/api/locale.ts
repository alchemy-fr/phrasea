import apiClient from './api-client';
import {SelectOption} from '@alchemy/react-form';
import i18n from '../i18n.ts';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';

export type Locale = {
    id: string;
    language: string;
    region: string;
    script: string;
    name: string;
    nativeName: string;
};

type LocaleAwareCache = Record<string, Locale[] | Promise<Locale[]>>;

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

    return (cache[currentLocale] = doGetLocales().then(res => {
        return (cache[currentLocale] = res.result);
    }) as Promise<Locale[]>);
}

async function doGetLocales(): Promise<NormalizedCollectionResponse<Locale>> {
    const res = await apiClient.get('/locales');

    return getHydraCollection(res.data);
}
